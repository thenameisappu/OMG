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

// Delete image helper (cleans local storage for unused uploaded images)
function deleteLocalImage($imagePath)
{
    if (empty($imagePath)) return;
    
    // Convert absolute URL to relative path if needed
    // e.g., http://localhost:8000/backend/uploads/filename.jpg -> uploads/filename.jpg
    $filename = basename($imagePath);
    $localPath = __DIR__ . '/uploads/' . $filename;
    
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
    $uploadDir = 'uploads/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    if (!isset($_FILES['files'])) {
        http_response_code(400);
        echo json_encode(["message" => "No files uploaded"]);
        return;
    }

    $files = $_FILES['files'];
    $urls = [];
    $errors = [];

    // Verify multiple file uploads
    $fileCount = is_array($files['name']) ? count($files['name']) : 1;

    for ($i = 0; $i < $fileCount; $i++) {
        $tmpName = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
        $name = is_array($files['name']) ? $files['name'][$i] : $files['name'];
        $size = is_array($files['size']) ? $files['size'][$i] : $files['size'];
        $error = is_array($files['error']) ? $files['error'][$i] : $files['error'];

        if ($error !== UPLOAD_ERR_OK) {
            $errors[] = "Error uploading file: $name";
            continue;
        }

        // Size check (max 5MB)
        if ($size > 5 * 1024 * 1024) {
            $errors[] = "File is too large: $name. Max limit is 5MB.";
            continue;
        }

        // Validate MIME type
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($tmpName);
        $allowedMimeTypes = [
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/gif',
            'image/webp'
        ];

        if (!in_array($mimeType, $allowedMimeTypes)) {
            $errors[] = "Invalid type: $name. Only JPG, PNG, GIF, and WEBP allowed.";
            continue;
        }

        // Secure file naming
        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $uniqueName = uniqid('prod_', true) . '.' . $extension;
        $targetFile = $uploadDir . $uniqueName;

        if (move_uploaded_file($tmpName, $targetFile)) {
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
            $host = $_SERVER['HTTP_HOST'];
            $baseDir = dirname($_SERVER['SCRIPT_NAME']);
            $url = $protocol . "://" . $host . $baseDir . "/" . $targetFile;
            $urls[] = $url;
        } else {
            $errors[] = "Failed to store file: $name";
        }
    }

    if (empty($urls)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Upload failed", "errors" => $errors]);
    } else {
        echo json_encode(["success" => true, "urls" => $urls, "errors" => $errors]);
    }
}
