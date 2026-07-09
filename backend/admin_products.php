<?php
require_once 'config.php';

// --- ADMIN AUTHENTICATION ---
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$database = new Database();
$db = $database->getConnection();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_orders.php");
    exit();
}

$is_main_admin = ($_SESSION['admin_username'] ?? '') === 'main_admin';

$message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
$warning = isset($_SESSION['warning_message']) ? $_SESSION['warning_message'] : '';
$error = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';

unset($_SESSION['success_message']);
unset($_SESSION['warning_message']);
unset($_SESSION['error_message']);

// --- SLUG & UUID HELPERS ---
function generateSlug($name) {
    $text = preg_replace('~[^\pL\d]+~u', '-', $name);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    return empty($text) ? 'n-a' : $text;
}

function makeUniqueSlug($db, $name, $excludeId = null) {
    $baseSlug = generateSlug($name);
    $slug = $baseSlug;
    $i = 1;
    while (true) {
        $query = "SELECT id FROM products WHERE slug = :slug";
        if ($excludeId) {
            $query .= " AND id != :exclude_id";
        }
        $stmt = $db->prepare($query);
        $stmt->bindParam(':slug', $slug);
        if ($excludeId) {
            $stmt->bindParam(':exclude_id', $excludeId);
        }
        $stmt->execute();
        if ($stmt->rowCount() == 0) {
            break;
        }
        $slug = $baseSlug . '-' . $i;
        $i++;
    }
    return $slug;
}

function generateUuid() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

function deleteLocalImage($imagePath) {
    if (empty($imagePath)) return;
    $filename = basename($imagePath);
    $localPath = __DIR__ . '/uploads/' . $filename;
    if (file_exists($localPath)) {
        @unlink($localPath);
    }
}

// --- FILE UPLOAD HELPERS ---
function handleFileUpload($fileKey, $existingUrl = '') {
    if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
        return $existingUrl;
    }
    $file = $_FILES[$fileKey];
    $size = $file['size'];
    $tmpName = $file['tmp_name'];
    $name = basename($file['name']);

    if ($size > 5 * 1024 * 1024) {
        throw new Exception("File is too large. Max limit is 5MB.");
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($tmpName);
    $allowedMimes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];

    if (!in_array($mimeType, $allowedMimes)) {
        throw new Exception("Invalid file type. Only JPG, PNG, GIF, and WEBP allowed.");
    }

    $uploadDir = 'uploads/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $extension = pathinfo($name, PATHINFO_EXTENSION);
    $uniqueName = uniqid('prod_', true) . '.' . $extension;
    $targetFile = $uploadDir . $uniqueName;

    if (move_uploaded_file($tmpName, $targetFile)) {
        if (!empty($existingUrl)) {
            deleteLocalImage($existingUrl);
        }
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'];
        $baseDir = dirname($_SERVER['SCRIPT_NAME']);
        return $protocol . "://" . $host . $baseDir . "/" . $targetFile;
    } else {
        throw new Exception("Failed to move uploaded file.");
    }
}

function handleMultipleFileUploads($fileKey, $existingImages = []) {
    if (!isset($_FILES[$fileKey]) || empty($_FILES[$fileKey]['name'][0])) {
        return $existingImages;
    }
    
    $files = $_FILES[$fileKey];
    $newImages = [];
    $fileCount = count($files['name']);
    
    for ($i = 0; $i < $fileCount; $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
        
        $tmpName = $files['tmp_name'][$i];
        $name = basename($files['name'][$i]);
        $size = $files['size'][$i];

        if ($size > 5 * 1024 * 1024) {
            throw new Exception("One of the additional images exceeds the 5MB size limit.");
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($tmpName);
        $allowedMimes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];

        if (!in_array($mimeType, $allowedMimes)) {
            throw new Exception("Invalid file type in additional images. Only JPG, PNG, GIF, and WEBP allowed.");
        }

        $uploadDir = 'uploads/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $uniqueName = uniqid('prod_', true) . '.' . $extension;
        $targetFile = $uploadDir . $uniqueName;

        if (move_uploaded_file($tmpName, $targetFile)) {
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
            $host = $_SERVER['HTTP_HOST'];
            $baseDir = dirname($_SERVER['SCRIPT_NAME']);
            $newImages[] = $protocol . "://" . $host . $baseDir . "/" . $targetFile;
        }
    }
    return array_merge($existingImages, $newImages);
}

// --- HANDLE POST ACTIONS (main_admin only) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!$is_main_admin) {
        $_SESSION['error_message'] = "Unauthorized. Only the main_admin can modify products.";
        header("Location: admin_products.php");
        exit();
    }

    $action = $_POST['action'];

    try {
        if ($action === 'create') {
            $name = trim($_POST['name'] ?? '');
            $price = (float)($_POST['price'] ?? 0);
            $category = trim($_POST['category'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $sku = trim($_POST['sku'] ?? '');
            $stock_quantity = (int)($_POST['stock_quantity'] ?? 0);
            $is_featured = isset($_POST['is_featured']) ? 1 : 0;
            $is_bestseller = isset($_POST['is_bestseller']) ? 1 : 0;
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            // Features input parsing
            $featuresArr = array_filter(array_map('trim', explode("\n", $_POST['features'] ?? '')));
            $features = json_encode(array_values($featuresArr));

            if (empty($name) || empty($category) || $price <= 0) {
                throw new Exception("Product Name, Category, and positive Price are required.");
            }

            // Image Uploads
            $image = handleFileUpload('product_image');
            $hover_image = handleFileUpload('product_hover_image');
            $add_images = handleMultipleFileUploads('additional_images', []);
            $images = json_encode($add_images);

            $id = generateUuid();
            $slug = makeUniqueSlug($db, $name);
            $stock_status = $stock_quantity > 0 ? 'in_stock' : 'out_of_stock';

            $stmt = $db->prepare("INSERT INTO products 
                (id, name, slug, description, price, category, image, hover_image, features, is_featured, is_bestseller, stock_status, stock_quantity, is_active, sku, images) 
                VALUES 
                (:id, :name, :slug, :description, :price, :category, :image, :hover_image, :features, :is_featured, :is_bestseller, :stock_status, :stock_quantity, :is_active, :sku, :images)");
            
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':slug', $slug);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':price', $price);
            $stmt->bindParam(':category', $category);
            $stmt->bindParam(':image', $image);
            $stmt->bindParam(':hover_image', $hover_image);
            $stmt->bindParam(':features', $features);
            $stmt->bindParam(':is_featured', $is_featured);
            $stmt->bindParam(':is_bestseller', $is_bestseller);
            $stmt->bindParam(':stock_status', $stock_status);
            $stmt->bindParam(':stock_quantity', $stock_quantity);
            $stmt->bindParam(':is_active', $is_active);
            $stmt->bindParam(':sku', $sku);
            $stmt->bindParam(':images', $images);

            $stmt->execute();
            $_SESSION['success_message'] = "Product '$name' created successfully!";

        } elseif ($action === 'update') {
            $id = trim($_POST['id'] ?? '');
            $name = trim($_POST['name'] ?? '');
            $price = (float)($_POST['price'] ?? 0);
            $category = trim($_POST['category'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $sku = trim($_POST['sku'] ?? '');
            $stock_quantity = (int)($_POST['stock_quantity'] ?? 0);
            $is_featured = isset($_POST['is_featured']) ? 1 : 0;
            $is_bestseller = isset($_POST['is_bestseller']) ? 1 : 0;
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            if (empty($id) || empty($name) || empty($category) || $price <= 0) {
                throw new Exception("Product ID, Name, Category, and positive Price are required.");
            }

            // Fetch current images
            $check = $db->prepare("SELECT image, hover_image, images FROM products WHERE id = :id");
            $check->bindParam(':id', $id);
            $check->execute();
            $curr = $check->fetch(PDO::FETCH_ASSOC);

            if (!$curr) {
                throw new Exception("Product not found.");
            }

            // Features parsing
            $featuresArr = array_filter(array_map('trim', explode("\n", $_POST['features'] ?? '')));
            $features = json_encode(array_values($featuresArr));

            // File uploads
            $image = handleFileUpload('product_image', $curr['image']);
            $hover_image = handleFileUpload('product_hover_image', $curr['hover_image']);
            
            // Delete selected additional images if specified
            $oldImages = !empty($curr['images']) ? json_decode($curr['images']) : [];
            $retainedImages = [];
            $deletedImages = $_POST['deleted_additional_images'] ?? [];
            foreach ($oldImages as $oldImg) {
                if (in_array($oldImg, $deletedImages)) {
                    deleteLocalImage($oldImg);
                } else {
                    $retainedImages[] = $oldImg;
                }
            }

            // Upload any new additional images
            $updatedImages = handleMultipleFileUploads('additional_images', $retainedImages);
            $images = json_encode($updatedImages);

            $slug = makeUniqueSlug($db, $name, $id);
            $stock_status = $stock_quantity > 0 ? 'in_stock' : 'out_of_stock';

            $stmt = $db->prepare("UPDATE products SET 
                name = :name, slug = :slug, description = :description, price = :price, category = :category, 
                image = :image, hover_image = :hover_image, features = :features, is_featured = :is_featured, 
                is_bestseller = :is_bestseller, stock_status = :stock_status, stock_quantity = :stock_quantity, 
                is_active = :is_active, sku = :sku, images = :images 
                WHERE id = :id");
            
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':slug', $slug);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':price', $price);
            $stmt->bindParam(':category', $category);
            $stmt->bindParam(':image', $image);
            $stmt->bindParam(':hover_image', $hover_image);
            $stmt->bindParam(':features', $features);
            $stmt->bindParam(':is_featured', $is_featured);
            $stmt->bindParam(':is_bestseller', $is_bestseller);
            $stmt->bindParam(':stock_status', $stock_status);
            $stmt->bindParam(':stock_quantity', $stock_quantity);
            $stmt->bindParam(':is_active', $is_active);
            $stmt->bindParam(':sku', $sku);
            $stmt->bindParam(':images', $images);

            $stmt->execute();
            $_SESSION['success_message'] = "Product '$name' updated successfully!";

        } elseif ($action === 'delete') {
            $id = trim($_POST['id'] ?? '');
            if (empty($id)) {
                throw new Exception("Product ID is required for deletion.");
            }

            // Fetch info
            $check = $db->prepare("SELECT name, image, hover_image, images FROM products WHERE id = :id");
            $check->bindParam(':id', $id);
            $check->execute();
            $prod = $check->fetch(PDO::FETCH_ASSOC);

            if (!$prod) {
                throw new Exception("Product not found.");
            }

            // Check orders
            $checkOrders = $db->prepare("SELECT COUNT(*) FROM order_items WHERE product_id = :id");
            $checkOrders->bindParam(':id', $id);
            $checkOrders->execute();
            $refs = (int)$checkOrders->fetchColumn();

            if ($refs > 0) {
                // Soft delete
                $stmt = $db->prepare("UPDATE products SET is_active = 0 WHERE id = :id");
                $stmt->bindParam(':id', $id);
                $stmt->execute();
                $_SESSION['warning_message'] = "Product '" . htmlspecialchars($prod['name']) . "' is referenced in existing customer orders. It has been set to Inactive (soft-deleted) to protect historical data.";
            } else {
                // Hard delete
                $stmt = $db->prepare("DELETE FROM products WHERE id = :id");
                $stmt->bindParam(':id', $id);
                $stmt->execute();

                deleteLocalImage($prod['image']);
                deleteLocalImage($prod['hover_image']);
                $addImgs = !empty($prod['images']) ? json_decode($prod['images']) : [];
                foreach ($addImgs as $img) {
                    deleteLocalImage($img);
                }
                $_SESSION['success_message'] = "Product permanently deleted.";
            }

        } elseif ($action === 'quick_stock') {
            $id = trim($_POST['id'] ?? '');
            $stock_quantity = (int)($_POST['stock_quantity'] ?? 0);

            if (empty($id)) {
                throw new Exception("Product ID is required.");
            }

            $stock_status = $stock_quantity > 0 ? 'in_stock' : 'out_of_stock';
            $stmt = $db->prepare("UPDATE products SET stock_quantity = :qty, stock_status = :status WHERE id = :id");
            $stmt->bindParam(':qty', $stock_quantity);
            $stmt->bindParam(':status', $stock_status);
            $stmt->bindParam(':id', $id);
            $stmt->execute();

            $_SESSION['success_message'] = "Stock inventory updated successfully!";
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }

    header("Location: admin_products.php");
    exit();
}

// --- FETCH PRODUCTS WITH SEARCH & FILTER ---
$search = trim($_GET['search'] ?? '');
$categoryFilter = trim($_GET['category'] ?? '');

$queryStr = "SELECT * FROM products WHERE 1=1";
$params = [];

if (!empty($search)) {
    $queryStr .= " AND (name LIKE :search OR description LIKE :search OR sku LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}
if (!empty($categoryFilter)) {
    $queryStr .= " AND category = :category";
    $params[':category'] = $categoryFilter;
}
$queryStr .= " ORDER BY created_at DESC";

$stmt = $db->prepare($queryStr);
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Map categories for rendering
$categoryNames = [
    'flower-arrangements' => "Oh My Bloom's",
    'gift-hampers' => "Oh My Love's",
    'signature-collection' => "Oh My Signature's",
    'occasions' => "Oh My Celebration's",
    'custom-orders' => "Oh My Customisation's"
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="120">
    <title>Admin - Product Management</title>
    <style>
        body { font-family: sans-serif; padding: 20px; background: #f4f4f4; margin: 0; }
        .container { max-width: 1200px; margin: 20px auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .logout-btn { color: red; text-decoration: none; font-weight: bold; }
        .nav-tabs { display: flex; gap: 20px; border-bottom: 2px solid #eee; margin-bottom: 20px; }
        .nav-tabs a { padding: 10px 15px; text-decoration: none; color: #555; font-weight: bold; border-bottom: 2px solid transparent; }
        .nav-tabs a.active { color: #000; border-bottom: 2px solid #000; }
        .nav-tabs a:hover { color: #000; }
        
        .toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; gap: 15px; flex-wrap: wrap; }
        .filters { display: flex; gap: 10px; align-items: center; }
        .filters input, .filters select { padding: 8px 12px; border: 1px solid #ccc; border-radius: 4px; font-size: 0.9em; }
        .btn-gold { padding: 9px 20px; background: #d4af37; color: #111; border: none; border-radius: 4px; font-weight: bold; cursor: pointer; transition: background 0.3s; }
        .btn-gold:hover { background: #b8932c; }
        .btn-outline { padding: 8px 15px; border: 1px solid #d4af37; background: transparent; color: #d4af37; border-radius: 4px; cursor: pointer; font-weight: bold; text-decoration: none; }
        .btn-outline:hover { background: #d4af37; color: #111; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 12px; border-bottom: 1px solid #ddd; text-align: left; vertical-align: middle; }
        th { background: #eee; font-weight: bold; }
        .prod-thumb { width: 50px; height: 50px; object-fit: cover; border-radius: 4px; border: 1px solid #ddd; }
        
        .badge { display: inline-block; padding: 4px 8px; border-radius: 20px; font-size: 0.8em; font-weight: bold; }
        .badge-success { background: #e2fcdb; color: #276d1a; }
        .badge-warning { background: #fff3cd; color: #856404; }
        .badge-danger { background: #f8d7da; color: #721c24; }
        .badge-info { background: #d1ecf1; color: #0c5460; }
        
        .action-links { display: flex; gap: 10px; }
        .action-link { text-decoration: none; font-weight: bold; font-size: 0.9em; cursor: pointer; }
        .link-edit { color: #0056b3; }
        .link-delete { color: #dc3545; }
        .link-stock { color: #28a745; }
        
        .alert { padding: 12px 20px; border-radius: 4px; margin-bottom: 20px; font-weight: bold; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-warning { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        /* Modal Styles */
        .modal { display: none; position: fixed; z-index: 10000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.6); align-items: center; justify-content: center; }
        .modal-content { background-color: #fefefe; margin: auto; padding: 25px; border: 1px solid #888; width: 90%; max-width: 600px; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.3); position: relative; max-height: 90vh; overflow-y: auto; }
        .close { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; position: absolute; right: 20px; top: 15px; }
        .close:hover { color: black; }
        
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; color: #333; }
        .form-group input[type="text"], .form-group input[type="number"], .form-group select, .form-group textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .form-group textarea { height: 80px; resize: vertical; }
        .form-row { display: flex; gap: 15px; }
        .form-row .form-group { flex: 1; }
        .form-group-checkbox { display: flex; gap: 20px; margin-top: 10px; }
        .form-group-checkbox label { display: flex; align-items: center; gap: 5px; cursor: pointer; }
        .image-preview-container { display: flex; gap: 10px; margin-top: 5px; flex-wrap: wrap; }
        .img-preview { width: 60px; height: 60px; object-fit: cover; border-radius: 4px; border: 1px solid #ccc; position: relative; }
        .img-preview-delete { position: absolute; top: -5px; right: -5px; background: red; color: white; border-radius: 50%; width: 18px; height: 18px; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: bold; cursor: pointer; border: 1px solid white; }
    </style>
</head>
<body>

<div class="container">
    <div class="header-row">
        <h1>Admin Product Management</h1>
        <div>
            <span style="margin-right:15px;">Welcome, <strong><?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin'); ?></strong></span>
            <a href="admin_orders.php?logout=true" class="logout-btn">Logout</a>
        </div>
    </div>

    <div class="nav-tabs">
        <a href="admin_orders.php">Orders</a>
        <a href="admin_inquiries.php">Inquiries</a>
        <a href="admin_customisations.php">Customisations</a>
        <a href="admin_products.php" class="active">Products</a>
        <?php if (($_SESSION['admin_username'] ?? '') === 'main_admin'): ?>
            <a href="admin_manage.php">Manage Admins</a>
        <?php endif; ?>
    </div>

    <!-- Feedback Alerts -->
    <?php if ($message): ?>
        <div class="alert alert-success"><?php echo $message; ?></div>
    <?php endif; ?>
    <?php if ($warning): ?>
        <div class="alert alert-warning"><?php echo $warning; ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <!-- Tool bar with Search/Filter and Add Buttons -->
    <div class="toolbar">
        <form method="GET" class="filters">
            <input type="text" name="search" placeholder="Search by name, SKU..." value="<?php echo htmlspecialchars($search); ?>">
            <select name="category">
                <option value="">All Categories</option>
                <?php foreach ($categoryNames as $key => $val): ?>
                    <option value="<?php echo $key; ?>" <?php echo $categoryFilter === $key ? 'selected' : ''; ?>><?php echo $val; ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn-gold">Filter</button>
            <?php if (!empty($search) || !empty($categoryFilter)): ?>
                <a href="admin_products.php" class="btn-outline">Reset</a>
            <?php endif; ?>
        </form>

        <?php if ($is_main_admin): ?>
            <button onclick="openAddModal()" class="btn-gold">+ Add New Product</button>
        <?php endif; ?>
    </div>

    <!-- Product list table -->
    <table>
        <thead>
            <tr>
                <th>Thumbnail</th>
                <th>Name / SKU</th>
                <th>Category</th>
                <th>Price</th>
                <th>Stock Status / Qty</th>
                <th>Badges</th>
                <th>Status</th>
                <?php if ($is_main_admin): ?>
                    <th>Actions</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($products)): ?>
                <tr>
                    <td colspan="<?php echo $is_main_admin ? 8 : 7; ?>" style="text-align:center; padding: 40px; color: #777;">No products found.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($products as $p): ?>
                    <tr>
                        <td>
                            <?php if (!empty($p['image'])): ?>
                                <img src="<?php echo htmlspecialchars($p['image']); ?>" class="prod-thumb" alt="Product thumbnail">
                            <?php else: ?>
                                <span style="font-size: 24px;">🌸</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars($p['name']); ?></strong><br>
                            <small style="color: #666; font-family: monospace;">SKU: <?php echo htmlspecialchars($p['sku'] ?? 'N/A'); ?></small>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($categoryNames[$p['category']] ?? $p['category']); ?>
                        </td>
                        <td>
                            ₹<?php echo number_format($p['price'], 2); ?>
                        </td>
                        <td>
                            <?php 
                            $qty = (int)$p['stock_quantity'];
                            if ($p['stock_status'] === 'out_of_stock' || $qty <= 0) {
                                echo '<span class="badge badge-danger">Out of Stock</span>';
                            } elseif ($qty <= 5) {
                                echo '<span class="badge badge-warning">Low Stock (' . $qty . ')</span>';
                            } else {
                                echo '<span class="badge badge-success">In Stock (' . $qty . ')</span>';
                            }
                            ?>
                        </td>
                        <td>
                            <?php if ($p['is_featured']): ?>
                                <span class="badge badge-info" style="margin-bottom: 2px;">Featured</span><br>
                            <?php endif; ?>
                            <?php if ($p['is_bestseller']): ?>
                                <span class="badge badge-gold" style="background:#fcf8e3; color:#a68300; border:1px solid #fbeed5;">Bestseller</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($p['is_active']): ?>
                                <span class="badge badge-success">Active</span>
                            <?php else: ?>
                                <span class="badge badge-danger">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <?php if ($is_main_admin): ?>
                            <td>
                                <div class="action-links">
                                    <span class="action-link link-edit" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($p)); ?>)">Edit</span>
                                    <span class="action-link link-stock" onclick="openStockModal('<?php echo $p['id']; ?>', <?php echo $qty; ?>)">Stock</span>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this product? If it has order history, it will be soft-deleted.');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                        <button type="submit" class="action-link link-delete" style="background:none; border:none; padding:0; font-family:inherit; cursor:pointer;">Delete</button>
                                    </form>
                                </div>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- --- MODALS (only rendered/used by main_admin) --- -->
<?php if ($is_main_admin): ?>
    <!-- Add Product Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('addModal')">&times;</span>
            <h2 style="margin-top: 0; color: #d4af37;">+ Add New Product</h2>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="create">
                
                <div class="form-group">
                    <label>Product Name *</label>
                    <input type="text" name="name" required placeholder="Midnight Velvet Roses">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Category *</label>
                        <select name="category" required>
                            <option value="">Select Category</option>
                            <?php foreach ($categoryNames as $key => $val): ?>
                                <option value="<?php echo $key; ?>"><?php echo $val; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>SKU</label>
                        <input type="text" name="sku" placeholder="FL-ROSE-01">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Price (₹) *</label>
                        <input type="number" name="price" step="0.01" min="1" required placeholder="999.00">
                    </div>
                    <div class="form-group">
                        <label>Stock Quantity *</label>
                        <input type="number" name="stock_quantity" min="0" required value="10">
                    </div>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" placeholder="Exquisite floral arrangement details..."></textarea>
                </div>

                <div class="form-group">
                    <label>Features (One feature per line)</label>
                    <textarea name="features" placeholder="Premium Long-stem Roses&#10;Luxury Velvet Wrap&#10;Hand-delivered"></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Main Image File</label>
                        <input type="file" name="product_image" accept="image/*" onchange="previewImage(this, 'addImagePreview')">
                        <div id="addImagePreview" class="image-preview-container"></div>
                    </div>
                    <div class="form-group">
                        <label>Hover Image File</label>
                        <input type="file" name="product_hover_image" accept="image/*" onchange="previewImage(this, 'addHoverPreview')">
                        <div id="addHoverPreview" class="image-preview-container"></div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Additional Images (Multiple files)</label>
                    <input type="file" name="additional_images[]" accept="image/*" multiple onchange="previewMultipleImages(this, 'addMultiPreview')">
                    <div id="addMultiPreview" class="image-preview-container"></div>
                </div>

                <div class="form-group-checkbox">
                    <label><input type="checkbox" name="is_featured" value="1"> Featured Product</label>
                    <label><input type="checkbox" name="is_bestseller" value="1"> Bestseller</label>
                    <label><input type="checkbox" name="is_active" value="1" checked> Active & Visible</label>
                </div>

                <button type="submit" class="btn-gold" style="width:100%; margin-top:15px; padding:12px;">Create Product</button>
            </form>
        </div>
    </div>

    <!-- Edit Product Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('editModal')">&times;</span>
            <h2 style="margin-top: 0; color: #d4af37;">Edit Product</h2>
            <form id="editForm" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit_id">
                
                <div class="form-group">
                    <label>Product Name *</label>
                    <input type="text" name="name" id="edit_name" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Category *</label>
                        <select name="category" id="edit_category" required>
                            <?php foreach ($categoryNames as $key => $val): ?>
                                <option value="<?php echo $key; ?>"><?php echo $val; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>SKU</label>
                        <input type="text" name="sku" id="edit_sku">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Price (₹) *</label>
                        <input type="number" name="price" id="edit_price" step="0.01" min="1" required>
                    </div>
                    <div class="form-group">
                        <label>Stock Quantity *</label>
                        <input type="number" name="stock_quantity" id="edit_stock_quantity" min="0" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" id="edit_description"></textarea>
                </div>

                <div class="form-group">
                    <label>Features (One feature per line)</label>
                    <textarea name="features" id="edit_features"></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Replace Main Image</label>
                        <input type="file" name="product_image" accept="image/*" onchange="previewImage(this, 'editImagePreview')">
                        <div id="editImagePreview" class="image-preview-container"></div>
                    </div>
                    <div class="form-group">
                        <label>Replace Hover Image</label>
                        <input type="file" name="product_hover_image" accept="image/*" onchange="previewImage(this, 'editHoverPreview')">
                        <div id="editHoverPreview" class="image-preview-container"></div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Upload More Additional Images</label>
                    <input type="file" name="additional_images[]" accept="image/*" multiple onchange="previewMultipleImages(this, 'editMultiUploadPreview')">
                    <div id="editMultiUploadPreview" class="image-preview-container"></div>
                </div>

                <!-- Existing Additional Images with deletion checkboxes -->
                <div class="form-group">
                    <label>Existing Additional Images (Check to Delete)</label>
                    <div id="existingAdditionalImages" class="image-preview-container"></div>
                </div>

                <div class="form-group-checkbox">
                    <label><input type="checkbox" name="is_featured" id="edit_is_featured" value="1"> Featured Product</label>
                    <label><input type="checkbox" name="is_bestseller" id="edit_is_bestseller" value="1"> Bestseller</label>
                    <label><input type="checkbox" name="is_active" id="edit_is_active" value="1"> Active & Visible</label>
                </div>

                <button type="submit" class="btn-gold" style="width:100%; margin-top:15px; padding:12px;">Save Changes</button>
            </form>
        </div>
    </div>

    <!-- Quick Stock Modal -->
    <div id="stockModal" class="modal">
        <div class="modal-content" style="max-width: 320px;">
            <span class="close" onclick="closeModal('stockModal')">&times;</span>
            <h2 style="margin-top: 0; color: #28a745;">Quick Stock Update</h2>
            <form method="POST">
                <input type="hidden" name="action" value="quick_stock">
                <input type="hidden" name="id" id="stock_id">
                
                <div class="form-group">
                    <label>New Stock Quantity</label>
                    <input type="number" name="stock_quantity" id="stock_qty_input" min="0" required>
                </div>
                <button type="submit" class="btn-gold" style="width: 100%; background: #28a745; color: white;">Update Stock</button>
            </form>
        </div>
    </div>

    <script>
    // Preview single image file
    function previewImage(input, previewContainerId) {
        const container = document.getElementById(previewContainerId);
        container.innerHTML = '';
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const img = document.createElement('img');
                img.src = e.target.result;
                img.className = 'prod-thumb';
                img.style.width = '80px';
                img.style.height = '80px';
                container.appendChild(img);
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    // Preview multiple image files
    function previewMultipleImages(input, previewContainerId) {
        const container = document.getElementById(previewContainerId);
        container.innerHTML = '';
        if (input.files) {
            Array.from(input.files).forEach(file => {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.className = 'prod-thumb';
                    img.style.width = '60px';
                    img.style.height = '60px';
                    container.appendChild(img);
                }
                reader.readAsDataURL(file);
            });
        }
    }

    // Modal Control
    function openAddModal() {
        document.getElementById('addModal').style.display = 'flex';
    }

    function openStockModal(productId, currentQty) {
        document.getElementById('stock_id').value = productId;
        document.getElementById('stock_qty_input').value = currentQty;
        document.getElementById('stockModal').style.display = 'flex';
    }

    function openEditModal(product) {
        document.getElementById('edit_id').value = product.id;
        document.getElementById('edit_name').value = product.name;
        document.getElementById('edit_category').value = product.category;
        document.getElementById('edit_sku').value = product.sku || '';
        document.getElementById('edit_price').value = product.price;
        document.getElementById('edit_stock_quantity').value = product.stock_quantity;
        document.getElementById('edit_description').value = product.description || '';

        // Features textarea population
        let features = '';
        if (product.features) {
            try {
                let parsed = JSON.parse(product.features);
                if (Array.isArray(parsed)) features = parsed.join("\n");
            } catch (e) {
                features = product.features;
            }
        }
        document.getElementById('edit_features').value = features;

        // Checkboxes
        document.getElementById('edit_is_featured').checked = (parseInt(product.is_featured) === 1);
        document.getElementById('edit_is_bestseller').checked = (parseInt(product.is_bestseller) === 1);
        document.getElementById('edit_is_active').checked = (parseInt(product.is_active) === 1);

        // Previews reset
        document.getElementById('editImagePreview').innerHTML = product.image ? `<img src="${product.image}" class="prod-thumb" style="width: 80px; height: 80px;">` : '';
        document.getElementById('editHoverPreview').innerHTML = product.hover_image ? `<img src="${product.hover_image}" class="prod-thumb" style="width: 80px; height: 80px;">` : '';
        document.getElementById('editMultiUploadPreview').innerHTML = '';

        // Existing additional images list
        const existingContainer = document.getElementById('existingAdditionalImages');
        existingContainer.innerHTML = '';
        if (product.images) {
            try {
                let parsedImages = JSON.parse(product.images);
                if (Array.isArray(parsedImages)) {
                    parsedImages.forEach(imgUrl => {
                        const div = document.createElement('div');
                        div.style.position = 'relative';
                        div.innerHTML = `
                            <img src="${imgUrl}" class="prod-thumb" style="width: 60px; height: 60px;">
                            <label style="display:block; font-size:11px; color:red; text-align:center; cursor:pointer; margin-top:2px;">
                                <input type="checkbox" name="deleted_additional_images[]" value="${imgUrl}"> Delete
                            </label>
                        `;
                        existingContainer.appendChild(div);
                    });
                }
            } catch(e) {}
        }

        document.getElementById('editModal').style.display = 'flex';
    }

    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }

    window.onclick = function(event) {
        if (event.target.className === 'modal') {
            event.target.style.display = 'none';
        }
    }
    </script>
<?php endif; ?>

<!-- Polling script for notifications of new orders/inquiries/customisations -->
<script>
// Auto-refresh after 2 minutes (120 seconds)
setTimeout(() => {
    location.reload();
}, 120000);

// Check for new orders, inquiries, or customizations periodically
(function() {
    let initialData = null;
    let isInitialized = false;
    let reloadTimer = null;

    function playNotificationSound() {
        try {
            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (!AudioCtx) return;
            const audioCtx = new AudioCtx();
            
            function playNote(frequency, startTime, duration) {
                const osc = audioCtx.createOscillator();
                const gain = audioCtx.createGain();
                
                osc.connect(gain);
                gain.connect(audioCtx.destination);
                
                osc.type = 'sine';
                osc.frequency.setValueAtTime(frequency, startTime);
                
                gain.gain.setValueAtTime(0.001, startTime);
                gain.gain.linearRampToValueAtTime(0.2, startTime + 0.05);
                gain.gain.exponentialRampToValueAtTime(0.001, startTime + duration);
                
                osc.start(startTime);
                osc.stop(startTime + duration);
            }
            
            playNote(1318.51, audioCtx.currentTime, 0.8);
            playNote(1760.00, audioCtx.currentTime + 0.12, 1.2);
        } catch (e) {
            console.warn("Failed to play notification tone:", e);
        }
    }

    function showNotification(message) {
        let container = document.getElementById('omg-notification-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'omg-notification-container';
            container.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 99999;
                display: flex;
                flex-direction: column;
                gap: 10px;
                font-family: sans-serif;
            `;
            document.body.appendChild(container);
        }

        const toast = document.createElement('div');
        toast.style.cssText = `
            background: #111;
            color: #d4af37;
            border: 1px solid #d4af37;
            padding: 16px 24px;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 320px;
            transform: translateX(120%);
            transition: transform 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            font-weight: bold;
            letter-spacing: 0.5px;
        `;
        
        toast.innerHTML = '<span style="font-size: 20px;">🔔</span> <span>' + message + '</span>';
        container.appendChild(toast);

        setTimeout(() => {
            toast.style.transform = 'translateX(0)';
        }, 100);
    }

    async function checkUpdates() {
        try {
            const response = await fetch('admin_orders.php?action=get_latest_order');
            if (!response.ok) return;
            const data = await response.json();
            
            if (!isInitialized) {
                initialData = data;
                isInitialized = true;
            } else {
                let changed = false;
                let message = "";

                if (data.orders.latest_id !== initialData.orders.latest_id || data.orders.total_count !== initialData.orders.total_count) {
                    changed = true;
                    message = "New Order Received!";
                } else if (data.inquiries.latest_id !== initialData.inquiries.latest_id || data.inquiries.total_count !== initialData.inquiries.total_count) {
                    changed = true;
                    message = "New Inquiry Received!";
                } else if (data.customisations.latest_id !== initialData.customisations.latest_id || data.customisations.total_count !== initialData.customisations.total_count) {
                    changed = true;
                    message = "New Customisation Request!";
                }

                if (changed && !reloadTimer) {
                    playNotificationSound();
                    showNotification(message);
                    reloadTimer = setTimeout(() => {
                        location.reload();
                    }, 2500);
                }
            }
        } catch (e) {
            console.error("Error checking updates:", e);
        }
    }

    setInterval(checkUpdates, 10000);
    checkUpdates();
})();
</script>

</body>
</html>
