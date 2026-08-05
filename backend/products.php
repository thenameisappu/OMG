<?php
// PHP SESSION & Database Setup
require_once 'config.php';

header("Content-Type: application/json; charset=UTF-8");

$database = new Database();
$db = $database->getConnection();

if ($db === null) {
    http_response_code(500);
    echo json_encode(["message" => "Database connection failed."]);
    exit();
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

// Centralized role authorization helper
function requireMainAdmin()
{
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true || ($_SESSION['admin_username'] ?? '') !== 'main_admin') {
        http_response_code(403);
        echo json_encode(["message" => "Forbidden - Main Admin access required"]);
        exit();
    }
}

// Slug generation utility
function generateSlug($name)
{
    $text = preg_replace('~[^\pL\d]+~u', '-', $name);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    return empty($text) ? 'n-a' : $text;
}

// Ensure slug uniqueness
function makeUniqueSlug($db, $name, $excludeId = null)
{
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

// UUID generator
function generateUuid()
{
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

// Delete image helper — removes file from domains/omgproductsimages/ (Hostinger domains root)
function deleteLocalImage($imagePath)
{
    if (empty($imagePath)) return;

    // Accept a full URL, a relative path, or just a filename
    $filename  = basename($imagePath);
    // 3 levels up from backend/: backend → public_html → domain-folder → domains/
    $localPath = dirname(dirname(dirname(__DIR__))) . '/omgproductsimages/' . $filename;

    if (file_exists($localPath)) {
        @unlink($localPath);
    }
}

// SWITCH API ACTIONS
switch ($action) {
    case 'get_products':
        getProducts($db);
        break;
    case 'get_product':
        getProductBySlug($db);
        break;
    case 'get_featured':
        getFeaturedProducts($db);
        break;
    case 'search':
        searchProducts($db);
        break;
        
    // --- ADMIN WRITE CRUD APIs ---
    case 'add_product':
        requireMainAdmin();
        addProduct($db);
        break;
    case 'update_product':
        requireMainAdmin();
        updateProduct($db);
        break;
    case 'delete_product':
        requireMainAdmin();
        deleteProduct($db);
        break;
    case 'update_stock':
        requireMainAdmin();
        updateStock($db);
        break;
    case 'upload_images':
        requireMainAdmin();
        uploadImages();
        break;
    default:
        http_response_code(400);
        echo json_encode(["message" => "Invalid action"]);
        break;
}

// 1. Fetch Products
function getProducts($db)
{
    $category = isset($_GET['category']) && $_GET['category'] !== 'all' ? $_GET['category'] : null;
    $include_inactive = isset($_GET['include_inactive']) && $_GET['include_inactive'] == 1;

    // Check if session is logged in as admin to allow including inactive
    if ($include_inactive) {
        if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
            $include_inactive = false;
        }
    }

    $query = "SELECT * FROM products";
    $conditions = [];

    if (!$include_inactive) {
        $conditions[] = "is_active = 1";
    }
    if ($category) {
        $conditions[] = "category = :category";
    }

    if (!empty($conditions)) {
        $query .= " WHERE " . implode(" AND ", $conditions);
    }
    $query .= " ORDER BY created_at DESC";

    $stmt = $db->prepare($query);
    if ($category) {
        $stmt->bindParam(":category", $category);
    }
    $stmt->execute();

    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Parse features JSON for standard output consistency
    foreach ($products as &$p) {
        if (!empty($p['features'])) {
            $p['features'] = json_decode($p['features']);
        } else {
            $p['features'] = [];
        }
        if (!empty($p['images'])) {
            $p['images'] = json_decode($p['images']);
        } else {
            $p['images'] = [];
        }
    }

    echo json_encode($products);
}

// 2. Fetch Single Product
function getProductBySlug($db)
{
    $slug = isset($_GET['slug']) ? $_GET['slug'] : '';
    if (empty($slug)) {
        http_response_code(400);
        echo json_encode(["message" => "Slug is required"]);
        return;
    }

    $query = "SELECT * FROM products WHERE slug = :slug LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":slug", $slug);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Parse JSON fields
        if (!empty($product['features'])) {
            $product['features'] = json_decode($product['features']);
        } else {
            $product['features'] = [];
        }
        if (!empty($product['images'])) {
            $product['images'] = json_decode($product['images']);
        } else {
            $product['images'] = [];
        }
        
        echo json_encode($product);
    } else {
        http_response_code(404);
        echo json_encode(["message" => "Product not found"]);
    }
}

// 3. Fetch Featured Products
function getFeaturedProducts($db)
{
    $query = "SELECT * FROM products WHERE is_active = 1 AND (is_bestseller = 1 OR is_featured = 1) LIMIT 6";
    $stmt = $db->prepare($query);
    $stmt->execute();

    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($products as &$p) {
        if (!empty($p['features'])) {
            $p['features'] = json_decode($p['features']);
        } else {
            $p['features'] = [];
        }
        if (!empty($p['images'])) {
            $p['images'] = json_decode($p['images']);
        } else {
            $p['images'] = [];
        }
    }
    echo json_encode($products);
}

// 4. Search Products
function searchProducts($db)
{
    $searchTerm = isset($_GET['search']) ? $_GET['search'] : '';
    if (empty($searchTerm)) {
        echo json_encode([]);
        return;
    }

    $searchTerm = "%" . $searchTerm . "%";
    $query = "SELECT * FROM products WHERE is_active = 1 AND (name LIKE :search OR description LIKE :search)";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":search", $searchTerm);
    $stmt->execute();

    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($products as &$p) {
        if (!empty($p['features'])) {
            $p['features'] = json_decode($p['features']);
        } else {
            $p['features'] = [];
        }
        if (!empty($p['images'])) {
            $p['images'] = json_decode($p['images']);
        } else {
            $p['images'] = [];
        }
    }
    echo json_encode($products);
}

// 5. Add Product (POST API)
function addProduct($db)
{
    $data = json_decode(file_get_contents("php://input"));
    
    if (empty($data->name) || empty($data->price) || empty($data->category)) {
        http_response_code(400);
        echo json_encode(["message" => "Name, Category, and Price are required fields."]);
        return;
    }

    try {
        $id = generateUuid();
        $slug = makeUniqueSlug($db, $data->name);
        $name = trim($data->name);
        $description = isset($data->description) ? trim($data->description) : "";
        $price = (float)$data->price;
        $category = trim($data->category);
        $image = isset($data->image) ? trim($data->image) : "";
        $hover_image = isset($data->hover_image) ? trim($data->hover_image) : "";
        $sku = isset($data->sku) ? trim($data->sku) : null;
        
        $features = isset($data->features) ? json_encode($data->features) : json_encode([]);
        $images = isset($data->images) ? json_encode($data->images) : json_encode([]);
        
        $is_featured = isset($data->is_featured) ? (int)$data->is_featured : 0;
        $is_bestseller = isset($data->is_bestseller) ? (int)$data->is_bestseller : 0;
        $stock_quantity = isset($data->stock_quantity) ? (int)$data->stock_quantity : 0;
        $stock_status = $stock_quantity > 0 ? 'in_stock' : 'out_of_stock';
        $is_active = isset($data->is_active) ? (int)$data->is_active : 1;

        $query = "INSERT INTO products 
                  (id, name, slug, description, price, category, image, hover_image, features, is_featured, is_bestseller, stock_status, stock_quantity, is_active, sku, images) 
                  VALUES 
                  (:id, :name, :slug, :description, :price, :category, :image, :hover_image, :features, :is_featured, :is_bestseller, :stock_status, :stock_quantity, :is_active, :sku, :images)";
        
        $stmt = $db->prepare($query);
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

        if ($stmt->execute()) {
            echo json_encode(["success" => true, "message" => "Product added successfully", "id" => $id]);
        } else {
            throw new Exception("SQL execution failed");
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["message" => "Failed to add product: " . $e->getMessage()]);
    }
}

// 6. Update Product (POST API)
function updateProduct($db)
{
    $data = json_decode(file_get_contents("php://input"));
    
    if (empty($data->id) || empty($data->name) || empty($data->price) || empty($data->category)) {
        http_response_code(400);
        echo json_encode(["message" => "ID, Name, Category, and Price are required fields."]);
        return;
    }

    try {
        $id = trim($data->id);
        
        // Fetch current product images to handle replacement/cleanup
        $checkQ = "SELECT image, hover_image, images FROM products WHERE id = :id";
        $checkStmt = $db->prepare($checkQ);
        $checkStmt->bindParam(':id', $id);
        $checkStmt->execute();
        $currentProduct = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$currentProduct) {
            http_response_code(404);
            echo json_encode(["message" => "Product not found"]);
            return;
        }

        $slug = makeUniqueSlug($db, $data->name, $id);
        $name = trim($data->name);
        $description = isset($data->description) ? trim($data->description) : "";
        $price = (float)$data->price;
        $category = trim($data->category);
        
        $image = isset($data->image) ? trim($data->image) : "";
        $hover_image = isset($data->hover_image) ? trim($data->hover_image) : "";
        $sku = isset($data->sku) ? trim($data->sku) : null;
        
        $features = isset($data->features) ? json_encode($data->features) : json_encode([]);
        $images = isset($data->images) ? json_encode($data->images) : json_encode([]);
        
        $is_featured = isset($data->is_featured) ? (int)$data->is_featured : 0;
        $is_bestseller = isset($data->is_bestseller) ? (int)$data->is_bestseller : 0;
        $stock_quantity = isset($data->stock_quantity) ? (int)$data->stock_quantity : 0;
        $stock_status = $stock_quantity > 0 ? 'in_stock' : 'out_of_stock';
        $is_active = isset($data->is_active) ? (int)$data->is_active : 1;

        // Cleanup local images if they were replaced
        if (!empty($currentProduct['image']) && $currentProduct['image'] !== $image) {
            deleteLocalImage($currentProduct['image']);
        }
        if (!empty($currentProduct['hover_image']) && $currentProduct['hover_image'] !== $hover_image) {
            deleteLocalImage($currentProduct['hover_image']);
        }
        
        // Handle additional images deletion
        $oldImages = !empty($currentProduct['images']) ? json_decode($currentProduct['images']) : [];
        $newImages = isset($data->images) ? $data->images : [];
        foreach ($oldImages as $oldImg) {
            if (!in_array($oldImg, $newImages)) {
                deleteLocalImage($oldImg);
            }
        }

        $query = "UPDATE products SET 
                    name = :name,
                    slug = :slug,
                    description = :description,
                    price = :price,
                    category = :category,
                    image = :image,
                    hover_image = :hover_image,
                    features = :features,
                    is_featured = :is_featured,
                    is_bestseller = :is_bestseller,
                    stock_status = :stock_status,
                    stock_quantity = :stock_quantity,
                    is_active = :is_active,
                    sku = :sku,
                    images = :images
                  WHERE id = :id";
        
        $stmt = $db->prepare($query);
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

        if ($stmt->execute()) {
            echo json_encode(["success" => true, "message" => "Product updated successfully"]);
        } else {
            throw new Exception("SQL execution failed");
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["message" => "Failed to update product: " . $e->getMessage()]);
    }
}

// 7. Delete Product (POST API)
function deleteProduct($db)
{
    $data = json_decode(file_get_contents("php://input"));
    
    if (empty($data->id)) {
        http_response_code(400);
        echo json_encode(["message" => "Product ID is required"]);
        return;
    }

    try {
        $id = trim($data->id);

        // Fetch product information
        $q = "SELECT image, hover_image, images FROM products WHERE id = :id";
        $stmt = $db->prepare($q);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            http_response_code(404);
            echo json_encode(["message" => "Product not found"]);
            return;
        }

        // Check if referenced in order_items
        $checkQuery = "SELECT COUNT(*) FROM order_items WHERE product_id = :id";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->bindParam(':id', $id);
        $checkStmt->execute();
        $referencesCount = (int)$checkStmt->fetchColumn();

        if ($referencesCount > 0) {
            // Soft delete instead of hard delete to preserve order history integrity
            $softDeleteQ = "UPDATE products SET is_active = 0 WHERE id = :id";
            $softStmt = $db->prepare($softDeleteQ);
            $softStmt->bindParam(':id', $id);
            $softStmt->execute();

            echo json_encode([
                "success" => true,
                "soft_delete" => true,
                "message" => "Product is referenced in previous orders. It has been deactivated (soft-deleted) to preserve order history."
            ]);
        } else {
            // Permanent hard delete
            $deleteQ = "DELETE FROM products WHERE id = :id";
            $delStmt = $db->prepare($deleteQ);
            $delStmt->bindParam(':id', $id);
            $delStmt->execute();

            // Delete associated local images
            deleteLocalImage($product['image']);
            deleteLocalImage($product['hover_image']);
            
            $additionalImages = !empty($product['images']) ? json_decode($product['images']) : [];
            foreach ($additionalImages as $img) {
                deleteLocalImage($img);
            }

            echo json_encode([
                "success" => true,
                "soft_delete" => false,
                "message" => "Product permanently deleted."
            ]);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["message" => "Failed to delete product: " . $e->getMessage()]);
    }
}

// 8. Update Stock (POST API)
function updateStock($db)
{
    $data = json_decode(file_get_contents("php://input"));
    
    if (empty($data->id) || !isset($data->stock_quantity)) {
        http_response_code(400);
        echo json_encode(["message" => "Product ID and Stock Quantity are required"]);
        return;
    }

    try {
        $id = trim($data->id);
        $stock_quantity = (int)$data->stock_quantity;
        $stock_status = $stock_quantity > 0 ? 'in_stock' : 'out_of_stock';

        $query = "UPDATE products SET stock_quantity = :stock_quantity, stock_status = :stock_status WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':stock_quantity', $stock_quantity);
        $stmt->bindParam(':stock_status', $stock_status);

        if ($stmt->execute()) {
            echo json_encode(["success" => true, "message" => "Stock updated successfully", "stock_quantity" => $stock_quantity, "stock_status" => $stock_status]);
        } else {
            throw new Exception("SQL execution failed");
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["message" => "Failed to update stock: " . $e->getMessage()]);
    }
}

// 9. Upload Images (POST API)
function uploadImages()
{
    // Upload directory: Hostinger domains root, 3 levels above backend/
    // Disk path : /home/<user>/domains/omgproductsimages/
    // Web URL   : <IMAGES_BASE_URL>/omgproductsimages/<filename>
    $uploadDir    = dirname(dirname(dirname(__DIR__))) . '/omgproductsimages/';
    $imagesBaseUrl = rtrim(
        getenv('IMAGES_BASE_URL') ?: (
            $_ENV['IMAGES_BASE_URL'] ?? (
                (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
                . '://' . $_SERVER['HTTP_HOST']
            )
        ),
        '/'
    );

    if (!file_exists($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Upload directory could not be created. Check server permissions.']);
            return;
        }
    }

    if (!is_writable($uploadDir)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Upload directory is not writable. Check server permissions.']);
        return;
    }

    if (!isset($_FILES['files'])) {
        http_response_code(400);
        echo json_encode(['message' => 'No files uploaded']);
        return;
    }

    $files     = $_FILES['files'];
    $urls      = [];
    $errors    = [];

    // Allowed MIME types and extensions (no GIF per requirements)
    $allowedMimeTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
    $allowedExts      = ['jpg', 'jpeg', 'png', 'webp'];

    $fileCount = is_array($files['name']) ? count($files['name']) : 1;

    for ($i = 0; $i < $fileCount; $i++) {
        $tmpName   = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
        $origName  = is_array($files['name'])     ? $files['name'][$i]     : $files['name'];
        $size      = is_array($files['size'])      ? $files['size'][$i]     : $files['size'];
        $error     = is_array($files['error'])     ? $files['error'][$i]    : $files['error'];

        // ── PHP upload error ─────────────────────────────────────────────
        if ($error !== UPLOAD_ERR_OK) {
            $errors[] = "Upload error for '$origName' (PHP error code: $error).";
            continue;
        }

        // ── Size limit (5 MB) ────────────────────────────────────────────
        if ($size > 5 * 1024 * 1024) {
            $errors[] = "'$origName' is too large. Maximum allowed size is 5 MB.";
            continue;
        }

        // ── MIME-type validation ─────────────────────────────────────────
        $finfo    = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($tmpName);

        if (!in_array($mimeType, $allowedMimeTypes, true)) {
            $errors[] = "'$origName' has an invalid file type ('$mimeType'). Only JPG, JPEG, PNG, and WEBP are allowed.";
            continue;
        }

        // ── Extension validation ─────────────────────────────────────────
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExts, true)) {
            $errors[] = "'$origName' has an invalid extension ('.$ext'). Only .jpg, .jpeg, .png, and .webp are permitted.";
            continue;
        }

        // ── Generate unique filename ─────────────────────────────────────
        // Always store as .jpg since move_uploaded_file preserves the binary;
        // use the original extension so non-GD paths stay consistent.
        $uniqueName = uniqid('prod_', true) . '.' . $ext;
        $targetFile = $uploadDir . $uniqueName;

        // ── Secure move ──────────────────────────────────────────────────
        if (move_uploaded_file($tmpName, $targetFile)) {
            $url    = $imagesBaseUrl . '/omgproductsimages/' . $uniqueName;
            $urls[] = $url;
        } else {
            $errors[] = "Failed to store '$origName'. Check directory permissions.";
        }
    }

    if (empty($urls)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'All uploads failed.', 'errors' => $errors]);
    } else {
        echo json_encode(['success' => true, 'urls' => $urls, 'errors' => $errors]);
    }
}
