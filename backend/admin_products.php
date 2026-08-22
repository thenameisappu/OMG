<?php
require_once 'config.php';

// --- ADMIN AUTHENTICATION ---
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$database = new Database();
$db = $database->getConnection();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin.php");
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
if (!function_exists('generateSlug')) {
    function generateSlug($name)
    {
        $text = preg_replace('~[^\pL\d]+~u', '-', $name);
        if (function_exists('iconv')) {
            $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        }
        $text = preg_replace('~[^-\w]+~', '', $text);
        $text = trim($text, '-');
        $text = preg_replace('~-+~', '-', $text);
        $text = strtolower($text);
        return empty($text) ? 'n-a' : $text;
    }
}

if (!function_exists('makeUniqueSlug')) {
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
}

if (!function_exists('generateUuid')) {
    function generateUuid()
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }
}

if (!function_exists('deleteLocalImage')) {
    /**
     * deleteLocalImage()
     * Removes an image from BOTH storage locations.
     */
    function deleteLocalImage(string $imagePath): void
    {
        if (empty($imagePath))
            return;

        $filename = basename($imagePath);
        if (empty($filename) || $filename === '.' || $filename === '..')
            return;

        $primary = OMG_PRIMARY_DIR . $filename;
        if (is_file($primary)) {
            if (!unlink($primary)) {
                error_log('[OMG Delete] Failed to remove from permanent store: ' . $primary);
            }
        }

        $secondary = OMG_SECONDARY_DIR . $filename;
        if (is_file($secondary)) {
            @unlink($secondary);
        }
    }
}

if (!function_exists('cropToSquare1000')) {
    /**
     * cropToSquare1000()
     * Crop & resize any uploaded image to a 1000x1000px square.
     */
    function cropToSquare1000(string $tmpName, string $destPath): bool
    {
        if (!extension_loaded('gd')) {
            return move_uploaded_file($tmpName, $destPath);
        }

        $info = getimagesize($tmpName);
        if (!$info)
            return false;

        [$srcW, $srcH, $imgType] = [$info[0], $info[1], $info[2]];

        switch ($imgType) {
            case IMAGETYPE_JPEG:
                $src = imagecreatefromjpeg($tmpName);
                break;
            case IMAGETYPE_PNG:
                $src = imagecreatefrompng($tmpName);
                break;
            case IMAGETYPE_GIF:
                $src = imagecreatefromgif($tmpName);
                break;
            case IMAGETYPE_WEBP:
                $src = imagecreatefromwebp($tmpName);
                break;
            default:
                return false;
        }
        if (!$src)
            return false;

        $squareSize = min($srcW, $srcH);
        $cropX = (int) (($srcW - $squareSize) / 2);
        $cropY = (int) (($srcH - $squareSize) / 2);

        $dst = imagecreatetruecolor(1000, 1000);

        if ($imgType === IMAGETYPE_PNG || $imgType === IMAGETYPE_WEBP) {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
            imagefill($dst, 0, 0, $transparent);
        }

        imagecopyresampled($dst, $src, 0, 0, $cropX, $cropY, 1000, 1000, $squareSize, $squareSize);
        $result = imagejpeg($dst, $destPath, 90);

        imagedestroy($src);
        imagedestroy($dst);

        return $result;
    }
}

if (!function_exists('slugify')) {
    function slugify(string $text): string
    {
        $text = strtolower($text);
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        if (function_exists('iconv')) {
            $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        }
        $text = preg_replace('~[^-\w]+~', '', $text);
        $text = trim($text, '-');
        $text = preg_replace('~-+~', '-', $text);
        return empty($text) ? 'product' : $text;
    }
}

if (!function_exists('generateProductImageFilename')) {
    function generateProductImageFilename(string $productName, string $suffixTag, string $extension = 'jpg', ?string $exactOverwriteFilename = null): string
    {
        if (!empty($exactOverwriteFilename)) {
            return basename($exactOverwriteFilename);
        }

        $slug = slugify($productName);
        $baseName = $slug . '-' . $suffixTag;
        $extension = strtolower(ltrim($extension, '.'));
        if (empty($extension)) {
            $extension = 'jpg';
        }
        $filename = $baseName . '.' . $extension;

        $primaryDir = OMG_PRIMARY_DIR;
        $counter = 1;
        while (file_exists($primaryDir . $filename)) {
            $filename = $baseName . '-' . $counter . '.' . $extension;
            $counter++;
        }

        return $filename;
    }
}

if (!function_exists('handleFileUpload')) {
    function handleFileUpload(string $fileKey, string $productName = '', string $suffixTag = 'main', string $existingUrl = '', bool $keepSameFilename = false): string
    {
        if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
            return $existingUrl;
        }

        $file = $_FILES[$fileKey];
        $size = $file['size'];
        $tmpName = $file['tmp_name'];
        $origName = $file['name'];

        if ($size > 5 * 1024 * 1024) {
            throw new Exception('File is too large. Maximum allowed size is 5 MB.');
        }

        $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($tmpName);
        $allowedMimes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
        if (!in_array($mimeType, $allowedMimes, true)) {
            throw new Exception('Invalid file type. Only JPG, JPEG, PNG, and WEBP are allowed.');
        }

        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($ext, $allowedExts, true)) {
            throw new Exception("Invalid file extension ('.$ext'). Only .jpg, .jpeg, .png, and .webp are permitted.");
        }

        $primaryDir = OMG_PRIMARY_DIR;
        $secondaryDir = OMG_SECONDARY_DIR;

        if (!is_dir($primaryDir) && !mkdir($primaryDir, 0755, true)) {
            throw new Exception('Permanent image directory could not be created. Check server permissions.');
        }
        if (!is_writable($primaryDir)) {
            throw new Exception('Permanent image directory is not writable. Check server permissions.');
        }
        if (!is_dir($secondaryDir)) {
            @mkdir($secondaryDir, 0755, true);
        }

        $overwriteFilename = ($keepSameFilename && !empty($existingUrl)) ? basename($existingUrl) : null;
        $targetFilename = generateProductImageFilename(!empty($productName) ? $productName : 'product', $suffixTag, 'jpg', $overwriteFilename);

        $primaryTarget = $primaryDir . $targetFilename;
        $secondaryTarget = $secondaryDir . $targetFilename;

        if (!cropToSquare1000($tmpName, $primaryTarget)) {
            error_log('[OMG Upload] cropToSquare1000() failed for: ' . $origName);
            throw new Exception('Failed to process and save the uploaded image.');
        }

        if (is_writable($secondaryDir) && !copy($primaryTarget, $secondaryTarget)) {
            error_log('[OMG Upload] copy() to backend/uploads/ failed for: ' . $targetFilename);
        }

        if (!empty($existingUrl) && !$keepSameFilename && basename($existingUrl) !== $targetFilename) {
            deleteLocalImage($existingUrl);
        }

        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        return $protocol . '://' . $_SERVER['HTTP_HOST'] . OMG_IMG_URL_PATH . $targetFilename;
    }
}

if (!function_exists('handleMultipleFileUploads')) {
    function handleMultipleFileUploads(string $fileKey, string $productName = '', array $existingImages = []): array
    {
        if (!isset($_FILES[$fileKey]) || empty($_FILES[$fileKey]['name'][0])) {
            return $existingImages;
        }

        $files = $_FILES[$fileKey];
        $newImages = [];
        $fileCount = count($files['name']);
        $allowedMimes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
        $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];

        $primaryDir = OMG_PRIMARY_DIR;
        $secondaryDir = OMG_SECONDARY_DIR;

        if (!is_dir($primaryDir) && !mkdir($primaryDir, 0755, true)) {
            throw new Exception('Permanent image directory could not be created. Check server permissions.');
        }
        if (!is_writable($primaryDir)) {
            throw new Exception('Permanent image directory is not writable. Check server permissions.');
        }
        if (!is_dir($secondaryDir)) {
            @mkdir($secondaryDir, 0755, true);
        }

        $startingIndex = count($existingImages) + 1;

        for ($i = 0; $i < $fileCount; $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK)
                continue;

            $tmpName = $files['tmp_name'][$i];
            $origName = $files['name'][$i];
            $size = $files['size'][$i];

            if ($size > 5 * 1024 * 1024) {
                throw new Exception("'$origName' exceeds the 5 MB size limit.");
            }

            $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($tmpName);
            if (!in_array($mimeType, $allowedMimes, true)) {
                throw new Exception("'$origName' has an invalid file type.");
            }

            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedExts, true)) {
                throw new Exception("'$origName' has an invalid extension.");
            }

            $suffixTag = 'gallery-' . ($startingIndex + $i);
            $targetFilename = generateProductImageFilename(!empty($productName) ? $productName : 'product', $suffixTag, 'jpg');

            $primaryTarget = $primaryDir . $targetFilename;
            $secondaryTarget = $secondaryDir . $targetFilename;

            if (!cropToSquare1000($tmpName, $primaryTarget)) {
                error_log('[OMG Upload] cropToSquare1000() failed for: ' . $origName);
                continue;
            }

            if (is_writable($secondaryDir) && !copy($primaryTarget, $secondaryTarget)) {
                error_log('[OMG Upload] copy() to backend/uploads/ failed for: ' . $targetFilename);
            }

            $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
            $newImages[] = $protocol . '://' . $_SERVER['HTTP_HOST'] . OMG_IMG_URL_PATH . $targetFilename;
        }

        return array_merge($existingImages, $newImages);
    }
}



// --- HANDLE POST ACTIONS (main_admin only) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!$is_main_admin) {
        $_SESSION['error_message'] = "Unauthorized. Only main_admin can modify products.";
        header("Location: admin.php?tab=products");
        exit();
    }

    $action = $_POST['action'];

    try {
        if ($action === 'create') {
            $name = trim($_POST['name'] ?? '');
            $price = (float) ($_POST['price'] ?? 0);
            $category = trim($_POST['category'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $sku = trim($_POST['sku'] ?? '');
            $stock_quantity = max(0, (int) ($_POST['stock_quantity'] ?? 0));
            $is_featured = isset($_POST['is_featured']) ? 1 : 0;
            $is_bestseller = isset($_POST['is_bestseller']) ? 1 : 0;
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            $confirm_duplicate = isset($_POST['confirm_duplicate']) && $_POST['confirm_duplicate'] === '1';

            if (empty($name)) {
                throw new Exception("Product Name is required.");
            }
            if (empty($category)) {
                throw new Exception("Category is required.");
            }
            if ($price <= 0) {
                throw new Exception("Price must be a positive amount.");
            }

            // Duplicate Check on Backend (if not explicitly confirmed)
            if (!$confirm_duplicate) {
                $dupCheck = $db->prepare("SELECT id, name FROM products WHERE LOWER(name) = LOWER(:name) LIMIT 1");
                $dupCheck->execute([':name' => $name]);
                $existingDup = $dupCheck->fetch(PDO::FETCH_ASSOC);
                if ($existingDup) {
                    throw new Exception("A product named '" . htmlspecialchars($existingDup['name']) . "' already exists. Please confirm if you want to add a duplicate product.");
                }
            }

            // Features input parsing
            $featuresArr = array_filter(array_map('trim', explode("\n", $_POST['features'] ?? '')));
            $features = json_encode(array_values($featuresArr));

            // Image Uploads
            $image = handleFileUpload('product_image', $name, 'main');
            $hover_image = handleFileUpload('product_hover_image', $name, 'hover');
            $add_images = handleMultipleFileUploads('additional_images', $name, []);
            $images = json_encode($add_images);

            $id = generateUuid();
            $slug = makeUniqueSlug($db, $name);
            $stock_status = $stock_quantity > 0 ? 'in_stock' : 'out_of_stock';

            $stmt = $db->prepare("INSERT INTO products 
                (id, name, slug, description, price, category, image, hover_image, features, is_featured, is_bestseller, stock_status, stock_quantity, is_active, sku, images) 
                VALUES 
                (:id, :name, :slug, :description, :price, :category, :image, :hover_image, :features, :is_featured, :is_bestseller, :stock_status, :stock_quantity, :is_active, :sku, :images)");

            $stmt->execute([
                ':id' => $id,
                ':name' => $name,
                ':slug' => $slug,
                ':description' => $description,
                ':price' => $price,
                ':category' => $category,
                ':image' => $image,
                ':hover_image' => $hover_image,
                ':features' => $features,
                ':is_featured' => $is_featured,
                ':is_bestseller' => $is_bestseller,
                ':stock_status' => $stock_status,
                ':stock_quantity' => $stock_quantity,
                ':is_active' => $is_active,
                ':sku' => $sku,
                ':images' => $images
            ]);

            $_SESSION['success_message'] = "Product '$name' created successfully!";

        } elseif ($action === 'update') {
            $id = trim($_POST['id'] ?? '');
            $name = trim($_POST['name'] ?? '');
            $price = (float) ($_POST['price'] ?? 0);
            $category = trim($_POST['category'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $sku = trim($_POST['sku'] ?? '');
            $stock_quantity = max(0, (int) ($_POST['stock_quantity'] ?? 0));
            $is_featured = isset($_POST['is_featured']) ? 1 : 0;
            $is_bestseller = isset($_POST['is_bestseller']) ? 1 : 0;
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            if (empty($id)) {
                throw new Exception("Product ID is missing.");
            }
            if (empty($name)) {
                throw new Exception("Product Name is required.");
            }
            if (empty($category)) {
                throw new Exception("Category is required.");
            }
            if ($price <= 0) {
                throw new Exception("Price must be a positive amount.");
            }

            // Fetch current images
            $check = $db->prepare("SELECT image, hover_image, images FROM products WHERE id = :id");
            $check->execute([':id' => $id]);
            $curr = $check->fetch(PDO::FETCH_ASSOC);

            if (!$curr) {
                throw new Exception("Product not found.");
            }

            // Features parsing
            $featuresArr = array_filter(array_map('trim', explode("\n", $_POST['features'] ?? '')));
            $features = json_encode(array_values($featuresArr));

            // File uploads
            $image = handleFileUpload('product_image', $name, 'main', $curr['image']);
            $hover_image = handleFileUpload('product_hover_image', $name, 'hover', $curr['hover_image']);

            // Delete selected additional images if specified
            $oldImages = !empty($curr['images']) ? json_decode($curr['images'], true) : [];
            $retainedImages = [];
            $deletedImages = $_POST['deleted_additional_images'] ?? [];
            if (is_array($oldImages)) {
                foreach ($oldImages as $oldImg) {
                    if (in_array($oldImg, $deletedImages)) {
                        deleteLocalImage($oldImg);
                    } else {
                        $retainedImages[] = $oldImg;
                    }
                }
            }

            // Upload any new additional images
            $updatedImages = handleMultipleFileUploads('additional_images', $name, $retainedImages);
            $images = json_encode($updatedImages);

            $slug = makeUniqueSlug($db, $name, $id);
            $stock_status = $stock_quantity > 0 ? 'in_stock' : 'out_of_stock';

            $stmt = $db->prepare("UPDATE products SET 
                name = :name, slug = :slug, description = :description, price = :price, category = :category, 
                image = :image, hover_image = :hover_image, features = :features, is_featured = :is_featured, 
                is_bestseller = :is_bestseller, stock_status = :stock_status, stock_quantity = :stock_quantity, 
                is_active = :is_active, sku = :sku, images = :images 
                WHERE id = :id");

            $stmt->execute([
                ':id' => $id,
                ':name' => $name,
                ':slug' => $slug,
                ':description' => $description,
                ':price' => $price,
                ':category' => $category,
                ':image' => $image,
                ':hover_image' => $hover_image,
                ':features' => $features,
                ':is_featured' => $is_featured,
                ':is_bestseller' => $is_bestseller,
                ':stock_status' => $stock_status,
                ':stock_quantity' => $stock_quantity,
                ':is_active' => $is_active,
                ':sku' => $sku,
                ':images' => $images
            ]);

            $_SESSION['success_message'] = "Product '$name' updated successfully!";

        } elseif ($action === 'delete_single_image') {
            $id = trim($_POST['product_id'] ?? '');
            $imageType = trim($_POST['image_type'] ?? ''); // 'main', 'hover', 'gallery'
            $imageUrl = trim($_POST['image_url'] ?? '');

            if (empty($id) || empty($imageType) || empty($imageUrl)) {
                throw new Exception("Product ID, image type, and image URL are required.");
            }

            $stmt = $db->prepare("SELECT image, hover_image, images FROM products WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $prod = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$prod) {
                throw new Exception("Product not found.");
            }

            deleteLocalImage($imageUrl);

            if ($imageType === 'main') {
                $up = $db->prepare("UPDATE products SET image = '' WHERE id = :id");
                $up->execute([':id' => $id]);
            } elseif ($imageType === 'hover') {
                $up = $db->prepare("UPDATE products SET hover_image = '' WHERE id = :id");
                $up->execute([':id' => $id]);
            } elseif ($imageType === 'gallery') {
                $existing = !empty($prod['images']) ? json_decode($prod['images'], true) : [];
                if (is_array($existing)) {
                    $filtered = array_values(array_filter($existing, function ($url) use ($imageUrl) {
                        return basename($url) !== basename($imageUrl);
                    }));
                    $up = $db->prepare("UPDATE products SET images = :images WHERE id = :id");
                    $up->execute([':images' => json_encode($filtered), ':id' => $id]);
                }
            }

            if (isset($_POST['is_ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest')) {
                header("Content-Type: application/json");
                echo json_encode(['success' => true, 'message' => 'Image deleted successfully.']);
                exit();
            }

            $_SESSION['success_message'] = "Image deleted successfully.";

        } elseif ($action === 'replace_single_image') {
            $id = trim($_POST['product_id'] ?? '');
            $imageType = trim($_POST['image_type'] ?? '');
            $existingUrl = trim($_POST['existing_url'] ?? '');

            if (empty($id) || empty($imageType)) {
                throw new Exception("Product ID and image type are required.");
            }

            $stmt = $db->prepare("SELECT name, image, hover_image, images FROM products WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $prod = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$prod) {
                throw new Exception("Product not found.");
            }

            $suffixTag = $imageType === 'main' ? 'main' : ($imageType === 'hover' ? 'hover' : 'gallery-1');
            $newUrl = handleFileUpload('replacement_file', $prod['name'], $suffixTag, $existingUrl, true);

            if ($imageType === 'main') {
                $up = $db->prepare("UPDATE products SET image = :image WHERE id = :id");
                $up->execute([':image' => $newUrl, ':id' => $id]);
            } elseif ($imageType === 'hover') {
                $up = $db->prepare("UPDATE products SET hover_image = :hover WHERE id = :id");
                $up->execute([':hover' => $newUrl, ':id' => $id]);
            } elseif ($imageType === 'gallery') {
                $existing = !empty($prod['images']) ? json_decode($prod['images'], true) : [];
                if (is_array($existing)) {
                    $updatedGallery = array_map(function ($url) use ($existingUrl, $newUrl) {
                        if (basename($url) === basename($existingUrl)) {
                            return $newUrl;
                        }
                        return $url;
                    }, $existing);
                    if (!in_array($newUrl, $updatedGallery)) {
                        $updatedGallery[] = $newUrl;
                    }
                    $up = $db->prepare("UPDATE products SET images = :images WHERE id = :id");
                    $up->execute([':images' => json_encode(array_values($updatedGallery)), ':id' => $id]);
                }
            }

            if (isset($_POST['is_ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest')) {
                header("Content-Type: application/json");
                echo json_encode(['success' => true, 'message' => 'Image replaced successfully.', 'new_url' => $newUrl]);
                exit();
            }

            $_SESSION['success_message'] = "Image replaced successfully.";


        } elseif ($action === 'delete') {
            $id = trim($_POST['id'] ?? '');
            if (empty($id)) {
                throw new Exception("Product ID is required for deletion.");
            }

            // Fetch info
            $check = $db->prepare("SELECT name, image, hover_image, images FROM products WHERE id = :id");
            $check->execute([':id' => $id]);
            $prod = $check->fetch(PDO::FETCH_ASSOC);

            if (!$prod) {
                throw new Exception("Product not found.");
            }

            // Check if referenced in customer orders
            $checkOrders = $db->prepare("SELECT COUNT(*) FROM order_items WHERE product_id = :id");
            $checkOrders->execute([':id' => $id]);
            $refs = (int) $checkOrders->fetchColumn();

            if ($refs > 0) {
                // Soft delete to protect historical order records
                $stmt = $db->prepare("UPDATE products SET is_active = 0 WHERE id = :id");
                $stmt->execute([':id' => $id]);
                $_SESSION['warning_message'] = "Product '" . htmlspecialchars($prod['name']) . "' is linked to existing customer orders. It has been set to Inactive (soft-deleted) to protect historical data.";
            } else {
                // Permanent deletion
                $stmt = $db->prepare("DELETE FROM products WHERE id = :id");
                $stmt->execute([':id' => $id]);

                deleteLocalImage($prod['image']);
                deleteLocalImage($prod['hover_image']);
                $addImgs = !empty($prod['images']) ? json_decode($prod['images'], true) : [];
                if (is_array($addImgs)) {
                    foreach ($addImgs as $img) {
                        deleteLocalImage($img);
                    }
                }
                $_SESSION['success_message'] = "Product '" . htmlspecialchars($prod['name']) . "' permanently deleted.";
            }

        } elseif ($action === 'quick_stock') {
            $id = trim($_POST['id'] ?? '');
            $stock_quantity = max(0, (int) ($_POST['stock_quantity'] ?? 0));

            if (empty($id)) {
                throw new Exception("Product ID is required.");
            }

            // Fetch product name
            $nameCheck = $db->prepare("SELECT name FROM products WHERE id = :id");
            $nameCheck->execute([':id' => $id]);
            $prodRow = $nameCheck->fetch(PDO::FETCH_ASSOC);
            $pName = $prodRow ? $prodRow['name'] : 'Product';

            $stock_status = $stock_quantity > 0 ? 'in_stock' : 'out_of_stock';
            $stmt = $db->prepare("UPDATE products SET stock_quantity = :qty, stock_status = :status WHERE id = :id");
            $stmt->execute([
                ':qty' => $stock_quantity,
                ':status' => $stock_status,
                ':id' => $id
            ]);

            $_SESSION['success_message'] = "Stock inventory updated for '$pName' (Qty: $stock_quantity, Status: $stock_status).";
        }
    } catch (Exception $e) {
        if (isset($_POST['is_ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest')) {
            header("Content-Type: application/json");
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit();
        }
        $_SESSION['error_message'] = $e->getMessage();
    }

    header("Location: admin.php?tab=products");
    exit();
}

// --- FETCH PRODUCTS WITH SEARCH & FILTER ---
$search = trim($_GET['search'] ?? '');
$categoryFilter = trim($_GET['category'] ?? '');

$queryStr = "SELECT * FROM products WHERE 1=1";
$params = [];

if (!empty($search)) {
    $queryStr .= " AND (name LIKE :search OR sku LIKE :search OR description LIKE :search)";
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

// Category Name Mapping
$categoryNames = [
    'flower-arrangements' => "Oh My Bloom's",
    'gift-hampers' => "Oh My Love's",
    'signature-collection' => "Oh My Signature's",
    'occasions' => "Oh My Celebration's",
    'custom-orders' => "Oh My Customisation's"
];

$pageTitle = "Product Catalog Management";
require_once 'admin_header.php';
?>

<div class="space-y-8">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-200 pb-6">
        <div>
            <h1 class="text-2xl sm:text-3xl font-serif font-bold text-slate-900">Product Catalog</h1>
            <p class="text-slate-500 text-sm mt-1">Manage floral products, pricing, categories, inventory & badges.</p>
        </div>
        <?php if ($is_main_admin): ?>
            <button onclick="openAddModal()"
                class="py-2.5 px-5 gold-gradient text-slate-950 font-bold rounded-xl text-sm shadow-md hover:shadow-lg transition-all flex items-center gap-2">
                <span> + </span> Add New Product
            </button>
        <?php endif; ?>
    </div>

    <!-- Feedback Alerts -->
    <?php if ($message): ?>
        <div
            class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl text-sm font-medium flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span>✅</span> <span><?php echo htmlspecialchars($message); ?></span>
            </div>
            <button onclick="this.parentElement.remove()"
                class="text-emerald-500 hover:text-emerald-800 font-bold">×</button>
        </div>
    <?php endif; ?>
    <?php if ($warning): ?>
        <div
            class="bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 rounded-xl text-sm font-medium flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span>⚠️</span> <span><?php echo htmlspecialchars($warning); ?></span>
            </div>
            <button onclick="this.parentElement.remove()" class="text-amber-500 hover:text-amber-800 font-bold">×</button>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div
            class="bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 rounded-xl text-sm font-medium flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span>❌</span> <span><?php echo htmlspecialchars($error); ?></span>
            </div>
            <button onclick="this.parentElement.remove()" class="text-rose-500 hover:text-rose-800 font-bold">×</button>
        </div>
    <?php endif; ?>

    <!-- Toolbar & Search Filters -->
    <div
        class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs flex flex-col sm:flex-row justify-between items-center gap-4">
        <form method="GET" action="admin.php" class="flex flex-wrap items-center gap-3 w-full sm:w-auto">
            <input type="hidden" name="tab" value="products">
            <input type="text" name="search" placeholder="Search by name, SKU..."
                value="<?php echo htmlspecialchars($search); ?>"
                class="px-4 py-2 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-amber-500 outline-none w-full sm:w-64">
            <select name="category"
                class="px-4 py-2 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-amber-500 outline-none bg-white">
                <option value="">All Categories</option>
                <?php foreach ($categoryNames as $key => $val): ?>
                    <option value="<?php echo $key; ?>" <?php echo $categoryFilter === $key ? 'selected' : ''; ?>>
                        <?php echo $val; ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit"
                class="py-2 px-4 bg-slate-900 text-white font-semibold rounded-xl text-sm hover:bg-slate-800 transition-colors">Filter</button>
            <?php if (!empty($search) || !empty($categoryFilter)): ?>
                <a href="admin.php?tab=products"
                    class="py-2 px-3 text-slate-500 hover:text-slate-800 text-sm font-medium">Reset</a>
            <?php endif; ?>
        </form>
        <span class="text-xs text-slate-500 font-medium">Showing <strong><?php echo count($products); ?></strong>
            products</span>
    </div>

    <!-- Product Table Card -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
        <div class="table-wrapper">
            <table class="w-full text-left text-sm border-collapse">
                <thead
                    class="bg-slate-100/70 text-slate-600 uppercase text-[11px] font-bold tracking-wider border-b border-slate-200">
                    <tr>
                        <th class="py-3.5 px-4 w-20">Thumbnail</th>
                        <th class="py-3.5 px-4">Name / SKU</th>
                        <th class="py-3.5 px-4">Category</th>
                        <th class="py-3.5 px-4">Price</th>
                        <th class="py-3.5 px-4">Stock Status / Qty</th>
                        <th class="py-3.5 px-4">Badges</th>
                        <th class="py-3.5 px-4">Status</th>
                        <?php if ($is_main_admin): ?>
                            <th class="py-3.5 px-4 text-right">Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($products)): ?>
                        <tr>
                            <td colspan="<?php echo $is_main_admin ? 8 : 7; ?>"
                                class="text-center py-12 text-slate-400 italic">No products found matching your search.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($products as $p): ?>
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <td class="py-3 px-4 align-middle w-20">
                                    <?php if (!empty($p['image'])): ?>
                                        <img src="<?php echo htmlspecialchars($p['image']); ?>"
                                            class="w-13 h-13 min-w-[52px] min-h-[52px] max-w-[52px] max-h-[52px] object-cover rounded-xl border border-slate-200 shadow-2xs prod-thumb"
                                            alt="Product thumbnail">
                                    <?php else: ?>
                                        <div
                                            class="w-13 h-13 min-w-[52px] min-h-[52px] rounded-xl bg-slate-100 border border-slate-200 flex items-center justify-center text-xl">
                                            🌸</div>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4 align-middle">
                                    <strong
                                        class="text-slate-900 font-bold text-sm block"><?php echo htmlspecialchars($p['name']); ?></strong>
                                    <span class="text-xs text-slate-500 font-mono">SKU:
                                        <?php echo htmlspecialchars($p['sku'] ?? 'N/A'); ?></span>
                                </td>
                                <td class="py-3 px-4 align-middle text-xs font-semibold text-slate-600">
                                    <?php echo htmlspecialchars($categoryNames[$p['category']] ?? $p['category']); ?>
                                </td>
                                <td class="py-3 px-4 align-middle font-bold text-slate-900 text-sm">
                                    ₹<?php echo number_format($p['price'], 2); ?>
                                </td>
                                <td class="py-3 px-4 align-middle text-xs">
                                    <?php
                                    $qty = (int) $p['stock_quantity'];
                                    if ($p['stock_status'] === 'out_of_stock' || $qty <= 0) {
                                        echo '<span class="px-2.5 py-1 rounded-full text-xs font-bold bg-rose-50 text-rose-700 border border-rose-200">Out of Stock (0)</span>';
                                    } elseif ($qty <= 5) {
                                        echo '<span class="px-2.5 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200">Low Stock (' . $qty . ')</span>';
                                    } else {
                                        echo '<span class="px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">In Stock (' . $qty . ')</span>';
                                    }
                                    ?>
                                </td>
                                <td class="py-3 px-4 align-middle text-xs">
                                    <?php if ($is_main_admin): ?>
                                        <button type="button" id="featured-btn-<?php echo $p['id']; ?>"
                                            data-product-id="<?php echo $p['id']; ?>"
                                            data-is-featured="<?php echo $p['is_featured'] ? '1' : '0'; ?>"
                                            onclick="toggleProductFeatured('<?php echo $p['id']; ?>', this)"
                                            title="Click to toggle Featured Product status"
                                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded font-semibold border text-[11px] mb-1 transition-all cursor-pointer hover:opacity-80 <?php echo $p['is_featured'] ? 'bg-sky-50 text-sky-700 border-sky-200' : 'bg-slate-50 text-slate-400 border-slate-200'; ?>">
                                            <span>Featured</span>
                                        </button><br>
                                    <?php else: ?>
                                        <?php if ($p['is_featured']): ?>
                                            <span
                                                class="inline-block px-2 py-0.5 rounded bg-sky-50 text-sky-700 font-semibold border border-sky-200 text-[11px] mb-1">Featured</span><br>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <?php if ($is_main_admin): ?>
                                        <button type="button" id="bestseller-btn-<?php echo $p['id']; ?>"
                                            data-product-id="<?php echo $p['id']; ?>"
                                            data-is-bestseller="<?php echo $p['is_bestseller'] ? '1' : '0'; ?>"
                                            onclick="toggleProductBestseller('<?php echo $p['id']; ?>', this)"
                                            title="Click to toggle Bestseller status"
                                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded font-semibold border text-[11px] transition-all cursor-pointer hover:opacity-80 <?php echo $p['is_bestseller'] ? 'bg-amber-50 text-amber-800 border-amber-200' : 'bg-slate-50 text-slate-400 border-slate-200'; ?>">
                                            <span>Bestseller</span>
                                        </button>
                                    <?php else: ?>
                                        <?php if ($p['is_bestseller']): ?>
                                            <span
                                                class="inline-block px-2 py-0.5 rounded bg-amber-50 text-amber-800 font-semibold border border-amber-200 text-[11px]">Bestseller</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4 align-middle text-xs">
                                    <?php if ($is_main_admin): ?>
                                        <!-- Interactive toggle for main_admin -->
                                        <button type="button" id="status-btn-<?php echo $p['id']; ?>"
                                            data-product-id="<?php echo $p['id']; ?>"
                                            data-is-active="<?php echo $p['is_active'] ? '1' : '0'; ?>"
                                            onclick="toggleProductStatus('<?php echo $p['id']; ?>', this)"
                                            title="Click to toggle Active / Inactive"
                                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold border transition-all cursor-pointer hover:opacity-80 focus:outline-none focus:ring-2 focus:ring-offset-1 <?php echo $p['is_active'] ? 'bg-emerald-50 text-emerald-700 border-emerald-200 focus:ring-emerald-400' : 'bg-slate-100 text-slate-500 border-slate-200 focus:ring-slate-400'; ?>">
                                            <span id="status-dot-<?php echo $p['id']; ?>"
                                                class="w-1.5 h-1.5 rounded-full <?php echo $p['is_active'] ? 'bg-emerald-500' : 'bg-slate-400'; ?>"></span>
                                            <span
                                                id="status-label-<?php echo $p['id']; ?>"><?php echo $p['is_active'] ? 'Active' : 'Inactive'; ?></span>
                                        </button>
                                    <?php else: ?>
                                        <!-- Read-only badge for non-main-admin -->
                                        <?php if ($p['is_active']): ?>
                                            <span
                                                class="px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">Active</span>
                                        <?php else: ?>
                                            <span
                                                class="px-2 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-500 border border-slate-200">Inactive</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <?php if ($is_main_admin): ?>
                                    <td class="py-3 px-4 align-middle text-right">
                                        <div class="flex items-center justify-end gap-2 text-xs font-bold">
                                            <button type="button"
                                                class="px-3 py-1.5 bg-slate-900 text-white rounded-lg hover:bg-slate-800 transition-colors shadow-2xs"
                                                onclick="openEditModal(<?php echo htmlspecialchars(json_encode($p)); ?>)">Edit</button>
                                            <button type="button"
                                                class="px-3 py-1.5 bg-amber-50 text-amber-800 border border-amber-200 rounded-lg hover:bg-amber-100 transition-colors"
                                                onclick="openStockModal('<?php echo $p['id']; ?>', '<?php echo htmlspecialchars(addslashes($p['name'])); ?>', <?php echo $qty; ?>)">Stock</button>
                                            <button type="button"
                                                class="px-3 py-1.5 bg-rose-50 text-rose-600 border border-rose-200 rounded-lg hover:bg-rose-100 transition-colors"
                                                onclick="openDeleteModal('<?php echo $p['id']; ?>', '<?php echo htmlspecialchars(addslashes($p['name'])); ?>', '<?php echo htmlspecialchars(addslashes($p['sku'] ?? '')); ?>')">Delete</button>
                                        </div>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- --- MODALS (only rendered for main_admin) --- -->
<?php if ($is_main_admin): ?>
    <!-- 1. ADD NEW PRODUCT MODAL -->
    <div id="addModal" class="fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-xs hidden items-center justify-center p-4">
        <div class="bg-white max-w-2xl w-full rounded-2xl p-6 shadow-2xl space-y-6 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <div class="flex items-center gap-2">
                    <span
                        class="w-8 h-8 rounded-lg gold-gradient flex items-center justify-center text-slate-950 font-bold text-sm">✨</span>
                    <h3 class="text-xl font-serif font-bold text-slate-900">Add New Product</h3>
                </div>
                <button type="button" onclick="closeModal('addModal')"
                    class="text-slate-400 hover:text-slate-700 text-xl font-bold">×</button>
            </div>

            <form id="addForm" method="POST" enctype="multipart/form-data" onsubmit="return handleAddSubmit(event)"
                class="space-y-4">
                <input type="hidden" name="action" value="create">
                <input type="hidden" name="confirm_duplicate" id="add_confirm_duplicate" value="0">

                <div class="space-y-1">
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">Product Name <span
                            class="text-rose-500">*</span></label>
                    <input type="text" name="name" id="add_name" required placeholder="e.g. Pink Box Bouquet"
                        class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-amber-500 text-sm outline-none">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">Category <span
                                class="text-rose-500">*</span></label>
                        <select name="category" id="add_category" required
                            class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-amber-500 text-sm outline-none bg-white">
                            <option value="">Select Category</option>
                            <?php foreach ($categoryNames as $key => $val): ?>
                                <option value="<?php echo $key; ?>"><?php echo $val; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="space-y-1">
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">SKU Code</label>
                        <input type="text" name="sku" id="add_sku" placeholder="e.g. FL-PINK-01"
                            class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-amber-500 text-sm outline-none">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">Price (₹) <span
                                class="text-rose-500">*</span></label>
                        <input type="number" name="price" id="add_price" step="0.01" min="1" required placeholder="3000.00"
                            class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-amber-500 text-sm outline-none">
                    </div>
                    <div class="space-y-1">
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">Initial Stock
                            Quantity <span class="text-rose-500">*</span></label>
                        <input type="number" name="stock_quantity" id="add_stock_quantity" min="0" required value="10"
                            class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-amber-500 text-sm outline-none">
                    </div>
                </div>

                <div class="space-y-1">
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">Description</label>
                    <textarea name="description" rows="3" placeholder="Exquisite floral arrangement details..."
                        class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-amber-500 text-sm outline-none"></textarea>
                </div>

                <div class="space-y-1">
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">Arrangement
                        Details</label>
                    <textarea name="features" rows="3"
                        placeholder="Fresh Pink Roses&#10;Signature Hat Box&#10;Hand-crafted Ribbon"
                        class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-amber-500 text-sm outline-none font-mono text-xs"></textarea>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">Main Product
                            Image</label>
                        <input type="file" name="product_image" accept="image/*"
                            onchange="previewImage(this, 'addImagePreview')"
                            class="text-xs text-slate-500 file:mr-3 file:py-2 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-amber-50 file:text-amber-800 hover:file:bg-amber-100">
                        <div id="addImagePreview" class="mt-2"></div>
                    </div>
                    <div class="space-y-1">
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">Hover Image
                            (Optional)</label>
                        <input type="file" name="product_hover_image" accept="image/*"
                            onchange="previewImage(this, 'addHoverPreview')"
                            class="text-xs text-slate-500 file:mr-3 file:py-2 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-amber-50 file:text-amber-800 hover:file:bg-amber-100">
                        <div id="addHoverPreview" class="mt-2"></div>
                    </div>
                </div>

                <div class="space-y-1">
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">Additional Gallery Images
                        (Multiple)</label>
                    <input type="file" name="additional_images[]" accept="image/*" multiple
                        onchange="previewMultipleImages(this, 'addMultiPreview')"
                        class="text-xs text-slate-500 file:mr-3 file:py-2 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-amber-50 file:text-amber-800 hover:file:bg-amber-100">
                    <div id="addMultiPreview" class="mt-2 flex flex-wrap gap-2"></div>
                </div>

                <div class="flex flex-wrap gap-6 pt-2">
                    <label class="flex items-center gap-2 text-xs font-bold text-slate-700 cursor-pointer">
                        <input type="checkbox" name="is_featured" value="1" class="w-4 h-4 text-amber-600 rounded"> Featured
                        Product
                    </label>
                    <label class="flex items-center gap-2 text-xs font-bold text-slate-700 cursor-pointer">
                        <input type="checkbox" name="is_bestseller" value="1" class="w-4 h-4 text-amber-600 rounded">
                        Bestseller Badge
                    </label>
                    <label class="flex items-center gap-2 text-xs font-bold text-slate-700 cursor-pointer">
                        <input type="checkbox" name="is_active" value="1" checked class="w-4 h-4 text-amber-600 rounded">
                        Active & Visible
                    </label>
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                    <button type="button" onclick="closeModal('addModal')"
                        class="py-2.5 px-5 rounded-xl border border-slate-300 text-slate-600 text-sm font-semibold hover:bg-slate-50">Cancel</button>
                    <button type="submit"
                        class="py-2.5 px-6 gold-gradient text-slate-950 font-bold rounded-xl text-sm shadow-md hover:shadow-lg transition-all">Create
                        Product</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 2. DUPLICATE PRODUCT WARNING MODAL -->
    <div id="duplicateModal"
        class="fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-xs hidden items-center justify-center p-4">
        <div class="bg-white max-w-md w-full rounded-2xl p-6 shadow-2xl space-y-4 text-center">
            <div
                class="w-14 h-14 bg-amber-100 border border-amber-200 rounded-full flex items-center justify-center mx-auto text-amber-600 text-2xl">
                ⚠️
            </div>
            <h3 class="text-lg font-serif font-bold text-slate-900">Duplicate Product Detected</h3>
            <p id="duplicateWarningText" class="text-xs text-slate-600 leading-relaxed px-2">
                This product already exists in your catalog. Are you sure you want to add it again?
            </p>
            <div class="flex items-center justify-center gap-3 pt-4 border-t border-slate-100">
                <button type="button" onclick="closeModal('duplicateModal')"
                    class="py-2.5 px-5 rounded-xl border border-slate-300 text-slate-700 text-sm font-semibold hover:bg-slate-50">Cancel</button>
                <button type="button" onclick="confirmAndSubmitDuplicate()"
                    class="py-2.5 px-5 bg-amber-500 hover:bg-amber-600 text-slate-950 font-bold rounded-xl text-sm shadow-md transition-all">Yes,
                    Continue Adding</button>
            </div>
        </div>
    </div>

    <!-- 3. EDIT PRODUCT MODAL -->
    <div id="editModal" class="fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-xs hidden items-center justify-center p-4">
        <div class="bg-white max-w-2xl w-full rounded-2xl p-6 shadow-2xl space-y-6 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <div class="flex items-center gap-2">
                    <span
                        class="w-8 h-8 rounded-lg bg-slate-900 text-amber-400 flex items-center justify-center font-bold text-sm">✏️</span>
                    <h3 class="text-xl font-serif font-bold text-slate-900">Edit Product Details</h3>
                </div>
                <button type="button" onclick="closeModal('editModal')"
                    class="text-slate-400 hover:text-slate-700 text-xl font-bold">×</button>
            </div>

            <form id="editForm" method="POST" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit_id">

                <div class="space-y-1">
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">Product Name <span
                            class="text-rose-500">*</span></label>
                    <input type="text" name="name" id="edit_name" required
                        class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-amber-500 text-sm outline-none">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">Category <span
                                class="text-rose-500">*</span></label>
                        <select name="category" id="edit_category" required
                            class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-amber-500 text-sm outline-none bg-white">
                            <?php foreach ($categoryNames as $key => $val): ?>
                                <option value="<?php echo $key; ?>"><?php echo $val; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="space-y-1">
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">SKU Code</label>
                        <input type="text" name="sku" id="edit_sku"
                            class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-amber-500 text-sm outline-none">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">Price (₹) <span
                                class="text-rose-500">*</span></label>
                        <input type="number" name="price" id="edit_price" step="0.01" min="1" required
                            class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-amber-500 text-sm outline-none">
                    </div>
                    <div class="space-y-1">
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">Stock Quantity <span
                                class="text-rose-500">*</span></label>
                        <input type="number" name="stock_quantity" id="edit_stock_quantity" min="0" required
                            class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-amber-500 text-sm outline-none">
                    </div>
                </div>

                <div class="space-y-1">
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">Description</label>
                    <textarea name="description" id="edit_description" rows="3"
                        class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-amber-500 text-sm outline-none"></textarea>
                </div>

                <div class="space-y-1">
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">Arrangement Details (One
                        feature per line)</label>
                    <textarea name="features" id="edit_features" rows="3"
                        class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-amber-500 text-sm outline-none font-mono text-xs"></textarea>
                </div>

                <!-- PRODUCT IMAGES MANAGEMENT SECTION -->
                <div class="border border-slate-200 rounded-2xl p-4 bg-slate-50/50 space-y-4">
                    <div class="flex items-center justify-between">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-slate-700 flex items-center gap-2">
                            <span>🖼️</span> Product Images Management
                        </h4>
                        <span class="text-[10px] text-slate-500 font-semibold">Saved in permanent & web folders</span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <!-- Main Image Box -->
                        <div class="bg-white p-3 rounded-xl border border-slate-200 space-y-2">
                            <span class="block text-[11px] font-bold uppercase tracking-wider text-slate-500">Main Product
                                Image</span>
                            <div id="mainImageContainer"></div>
                        </div>

                        <!-- Hover Image Box -->
                        <div class="bg-white p-3 rounded-xl border border-slate-200 space-y-2">
                            <span class="block text-[11px] font-bold uppercase tracking-wider text-slate-500">Hover
                                Image</span>
                            <div id="hoverImageContainer"></div>
                        </div>
                    </div>

                    <!-- Gallery Images Section -->
                    <div class="bg-white p-3 rounded-xl border border-slate-200 space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Gallery
                                Images</span>
                            <span id="galleryCountBadge" class="text-[10px] font-bold text-slate-400">0 images</span>
                        </div>
                        <div id="galleryImagesContainer" class="grid grid-cols-1 sm:grid-cols-2 gap-3"></div>

                        <!-- Upload Additional Gallery Images -->
                        <div class="pt-3 border-t border-slate-100 space-y-1">
                            <label class="block text-[11px] font-bold text-slate-700">Upload More Gallery Images</label>
                            <input type="file" name="additional_images[]" accept="image/*" multiple
                                onchange="previewMultipleImages(this, 'editMultiUploadPreview')"
                                class="text-xs text-slate-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-amber-50 file:text-amber-800 hover:file:bg-amber-100">
                            <div id="editMultiUploadPreview" class="mt-2 flex flex-wrap gap-2"></div>
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap gap-6 pt-2">
                    <label class="flex items-center gap-2 text-xs font-bold text-slate-700 cursor-pointer">
                        <input type="checkbox" name="is_featured" id="edit_is_featured" value="1"
                            class="w-4 h-4 text-amber-600 rounded"> Featured Product
                    </label>
                    <label class="flex items-center gap-2 text-xs font-bold text-slate-700 cursor-pointer">
                        <input type="checkbox" name="is_bestseller" id="edit_is_bestseller" value="1"
                            class="w-4 h-4 text-amber-600 rounded"> Bestseller Badge
                    </label>
                    <label class="flex items-center gap-2 text-xs font-bold text-slate-700 cursor-pointer">
                        <input type="checkbox" name="is_active" id="edit_is_active" value="1"
                            class="w-4 h-4 text-amber-600 rounded"> Active & Visible
                    </label>
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                    <button type="button" onclick="closeModal('editModal')"
                        class="py-2.5 px-5 rounded-xl border border-slate-300 text-slate-600 text-sm font-semibold hover:bg-slate-50">Cancel</button>
                    <button type="submit"
                        class="py-2.5 px-6 bg-slate-900 hover:bg-slate-800 text-white font-bold rounded-xl text-sm shadow-md transition-all">Save
                        Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 4. QUICK STOCK UPDATE MODAL -->
    <div id="stockModal" class="fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-xs hidden items-center justify-center p-4">
        <div class="bg-white max-w-sm w-full rounded-2xl p-6 shadow-2xl space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2">
                    <span
                        class="w-8 h-8 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold text-sm">📦</span>
                    <h3 class="text-lg font-serif font-bold text-slate-900">Update Inventory Stock</h3>
                </div>
                <button type="button" onclick="closeModal('stockModal')"
                    class="text-slate-400 hover:text-slate-700 text-xl font-bold">×</button>
            </div>

            <p class="text-xs text-slate-500">Product: <strong id="stock_product_name" class="text-slate-900"></strong></p>

            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="quick_stock">
                <input type="hidden" name="id" id="stock_id">

                <div class="space-y-1">
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">New Stock Quantity <span
                            class="text-rose-500">*</span></label>
                    <input type="number" name="stock_quantity" id="stock_qty_input" min="0" required
                        class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-emerald-500 text-sm outline-none">
                </div>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <button type="button" onclick="closeModal('stockModal')"
                        class="py-2 px-4 rounded-xl border border-slate-300 text-slate-600 text-xs font-semibold hover:bg-slate-50">Cancel</button>
                    <button type="submit"
                        class="py-2 px-5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs shadow-md transition-all">Update
                        Stock</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 5. CONFIRM DELETE MODAL -->
    <div id="deleteModal"
        class="fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-xs hidden items-center justify-center p-4">
        <div class="bg-white max-w-md w-full rounded-2xl p-6 shadow-2xl space-y-4 text-center">
            <div
                class="w-14 h-14 bg-rose-100 border border-rose-200 rounded-full flex items-center justify-center mx-auto text-rose-600 text-2xl">
                🗑️
            </div>
            <h3 class="text-lg font-serif font-bold text-slate-900">Confirm Product Deletion</h3>
            <p class="text-xs text-slate-600 leading-relaxed">
                Are you sure you want to delete <strong id="delete_product_name" class="text-slate-900"></strong>?
            </p>
            <p class="text-[11px] text-slate-400 bg-slate-50 p-2.5 rounded-xl border border-slate-100">
                ℹ️ If this product is linked to existing customer orders, it will be safely set to Inactive (soft-deleted)
                to preserve order history.
            </p>
            <form method="POST" class="flex items-center justify-center gap-3 pt-2">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="delete_product_id">
                <button type="button" onclick="closeModal('deleteModal')"
                    class="py-2.5 px-5 rounded-xl border border-slate-300 text-slate-700 text-sm font-semibold hover:bg-slate-50">Cancel</button>
                <button type="submit"
                    class="py-2.5 px-5 bg-rose-600 hover:bg-rose-700 text-white font-bold rounded-xl text-sm shadow-md transition-all">Yes,
                    Delete</button>
            </form>
        </div>
    </div>

    <!-- Hidden input for single image replacement -->
    <input type="file" id="global_image_replace_input" accept="image/*" class="hidden"
        onchange="handleImageReplacementSelected(event)">

    <script>
        // Existing Products Data for Duplicate Checking
        const existingProductsList = <?php echo json_encode(array_map(function ($p) {
            return ['id' => $p['id'], 'name' => strtolower($p['name']), 'sku' => strtolower($p['sku'] ?? '')];
        }, $products)); ?>;

        // Single image preview helper
        function previewImage(input, previewContainerId) {
            const container = document.getElementById(previewContainerId);
            if (!container) return;
            container.innerHTML = '';
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.className = 'w-16 h-16 object-cover rounded-xl border border-slate-200 shadow-2xs mt-1';
                    container.appendChild(img);
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Multiple image preview helper
        function previewMultipleImages(input, previewContainerId) {
            const container = document.getElementById(previewContainerId);
            if (!container) return;
            container.innerHTML = '';
            if (input.files) {
                Array.from(input.files).forEach(file => {
                    const reader = new FileReader();
                    reader.onload = function (e) {
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        img.className = 'w-14 h-14 object-cover rounded-xl border border-slate-200 shadow-2xs mt-1';
                        container.appendChild(img);
                    }
                    reader.readAsDataURL(file);
                });
            }
        }

        // ── Single Image Replace & Delete JS Handlers ──
        let currentReplaceData = null;

        function triggerReplaceImage(productId, imageType, existingUrl) {
            currentReplaceData = { productId, imageType, existingUrl };
            const input = document.getElementById('global_image_replace_input');
            input.value = '';
            input.click();
        }

        async function handleImageReplacementSelected(event) {
            const file = event.target.files[0];
            if (!file || !currentReplaceData) return;

            const { productId, imageType, existingUrl } = currentReplaceData;
            const filename = existingUrl ? existingUrl.split('/').pop() : 'image';

            const formData = new FormData();
            formData.append('action', 'replace_single_image');
            formData.append('product_id', productId);
            formData.append('image_type', imageType);
            formData.append('existing_url', existingUrl);
            formData.append('replacement_file', file);
            formData.append('is_ajax', '1');

            try {
                const response = await fetch('admin_products.php', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const result = await response.json();
                if (result.success) {
                    alert(`Success: ${filename} has been replaced.`);
                    location.reload();
                } else {
                    alert('Error: ' + (result.message || 'Failed to replace image.'));
                }
            } catch (e) {
                alert('Failed to replace image: ' + e.message);
            }
        }

        function confirmDeleteSingleImage(productId, imageType, imageUrl) {
            const filename = imageUrl.split('/').pop();
            if (!confirm(`Are you sure you want to delete "${filename}"?\nThis action removes the image permanently from both storage folders.`)) {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'delete_single_image');
            formData.append('product_id', productId);
            formData.append('image_type', imageType);
            formData.append('image_url', imageUrl);
            formData.append('is_ajax', '1');

            fetch('admin_products.php', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            }).then(r => r.json()).then(result => {
                if (result.success) {
                    alert(`Success: ${filename} deleted.`);
                    location.reload();
                } else {
                    alert('Error: ' + (result.message || 'Failed to delete image.'));
                }
            }).catch(e => {
                alert('Failed to delete image: ' + e.message);
            });
        }

        // Duplicate Check Handler on Add Form Submit
        function handleAddSubmit(event) {
            const confirmInput = document.getElementById('add_confirm_duplicate');
            if (confirmInput.value === '1') {
                return true;
            }

            const nameInput = document.getElementById('add_name').value.trim().toLowerCase();
            const skuInput = document.getElementById('add_sku').value.trim().toLowerCase();

            const isDuplicate = existingProductsList.some(p =>
                p.name === nameInput || (skuInput !== '' && p.sku === skuInput)
            );

            if (isDuplicate) {
                event.preventDefault();
                document.getElementById('duplicateWarningText').innerHTML =
                    `This product (<strong>"${document.getElementById('add_name').value}"</strong>) already exists in your catalog. Are you sure you want to add it again?`;
                openModal('duplicateModal');
                return false;
            }

            return true;
        }

        function confirmAndSubmitDuplicate() {
            document.getElementById('add_confirm_duplicate').value = '1';
            closeModal('duplicateModal');
            document.getElementById('addForm').submit();
        }

        // Modal Opening & Closing Control
        function openModal(id) {
            const el = document.getElementById(id);
            if (el) {
                el.classList.remove('hidden');
                el.classList.add('flex');
            }
        }

        function closeModal(id) {
            const el = document.getElementById(id);
            if (el) {
                el.classList.add('hidden');
                el.classList.remove('flex');
            }
        }

        function openAddModal() {
            document.getElementById('add_confirm_duplicate').value = '0';
            openModal('addModal');
        }

        function openStockModal(productId, productName, currentQty) {
            document.getElementById('stock_id').value = productId;
            document.getElementById('stock_product_name').textContent = productName;
            document.getElementById('stock_qty_input').value = currentQty;
            openModal('stockModal');
        }

        function openDeleteModal(productId, productName, sku) {
            document.getElementById('delete_product_id').value = productId;
            document.getElementById('delete_product_name').textContent = productName + (sku ? ` (SKU: ${sku})` : '');
            openModal('deleteModal');
        }

        function openEditModal(product) {
            document.getElementById('edit_id').value = product.id;
            document.getElementById('edit_name').value = product.name;
            document.getElementById('edit_category').value = product.category;
            document.getElementById('edit_sku').value = product.sku || '';
            document.getElementById('edit_price').value = product.price;
            document.getElementById('edit_stock_quantity').value = product.stock_quantity;
            document.getElementById('edit_description').value = product.description || '';

            // Features textarea
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

            // ── Render Main Image Box ──
            const mainContainer = document.getElementById('mainImageContainer');
            if (product.image) {
                const mainFilename = product.image.split('/').pop();
                mainContainer.innerHTML = `
                    <div class="flex items-center gap-3 p-2 bg-slate-50 rounded-xl border border-slate-200">
                        <img src="${product.image}" class="w-16 h-16 object-cover rounded-lg border border-slate-200 shadow-2xs shrink-0">
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-mono font-bold text-slate-700 truncate" title="${mainFilename}">${mainFilename}</p>
                            <div class="flex items-center gap-2 mt-2">
                                <button type="button" onclick="triggerReplaceImage('${product.id}', 'main', '${product.image}')" 
                                    class="px-2.5 py-1 text-[11px] font-bold bg-amber-100 hover:bg-amber-200 text-amber-900 rounded-lg transition-colors flex items-center gap-1">
                                    🔄 Replace
                                </button>
                                <button type="button" onclick="confirmDeleteSingleImage('${product.id}', 'main', '${product.image}')" 
                                    class="px-2.5 py-1 text-[11px] font-bold bg-rose-50 hover:bg-rose-100 text-rose-700 rounded-lg transition-colors flex items-center gap-1">
                                    🗑️ Delete
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            } else {
                mainContainer.innerHTML = `
                    <div class="p-3 text-center border border-dashed border-slate-200 rounded-xl bg-slate-50/50 space-y-2">
                        <span class="text-xs text-slate-400 italic block">No main image set</span>
                        <input type="file" name="product_image" accept="image/*" onchange="previewImage(this, 'editMainPreview')" class="hidden" id="edit_main_file_input">
                        <button type="button" onclick="document.getElementById('edit_main_file_input').click()" 
                            class="px-3 py-1 text-xs font-semibold bg-amber-50 hover:bg-amber-100 text-amber-800 rounded-lg border border-amber-200">
                            📁 Upload Main Image
                        </button>
                        <div id="editMainPreview" class="flex justify-center mt-1"></div>
                    </div>
                `;
            }

            // ── Render Hover Image Box ──
            const hoverContainer = document.getElementById('hoverImageContainer');
            if (product.hover_image) {
                const hoverFilename = product.hover_image.split('/').pop();
                hoverContainer.innerHTML = `
                    <div class="flex items-center gap-3 p-2 bg-slate-50 rounded-xl border border-slate-200">
                        <img src="${product.hover_image}" class="w-16 h-16 object-cover rounded-lg border border-slate-200 shadow-2xs shrink-0">
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-mono font-bold text-slate-700 truncate" title="${hoverFilename}">${hoverFilename}</p>
                            <div class="flex items-center gap-2 mt-2">
                                <button type="button" onclick="triggerReplaceImage('${product.id}', 'hover', '${product.hover_image}')" 
                                    class="px-2.5 py-1 text-[11px] font-bold bg-amber-100 hover:bg-amber-200 text-amber-900 rounded-lg transition-colors flex items-center gap-1">
                                    🔄 Replace
                                </button>
                                <button type="button" onclick="confirmDeleteSingleImage('${product.id}', 'hover', '${product.hover_image}')" 
                                    class="px-2.5 py-1 text-[11px] font-bold bg-rose-50 hover:bg-rose-100 text-rose-700 rounded-lg transition-colors flex items-center gap-1">
                                    🗑️ Delete
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            } else {
                hoverContainer.innerHTML = `
                    <div class="p-3 text-center border border-dashed border-slate-200 rounded-xl bg-slate-50/50 space-y-2">
                        <span class="text-xs text-slate-400 italic block">No hover image set</span>
                        <input type="file" name="product_hover_image" accept="image/*" onchange="previewImage(this, 'editHoverPreview')" class="hidden" id="edit_hover_file_input">
                        <button type="button" onclick="document.getElementById('edit_hover_file_input').click()" 
                            class="px-3 py-1 text-xs font-semibold bg-amber-50 hover:bg-amber-100 text-amber-800 rounded-lg border border-amber-200">
                            📁 Upload Hover Image
                        </button>
                        <div id="editHoverPreview" class="flex justify-center mt-1"></div>
                    </div>
                `;
            }

            // ── Render Gallery Images Grid ──
            const galleryContainer = document.getElementById('galleryImagesContainer');
            const badge = document.getElementById('galleryCountBadge');
            document.getElementById('editMultiUploadPreview').innerHTML = '';
            galleryContainer.innerHTML = '';

            let galleryList = [];
            if (product.images) {
                try {
                    galleryList = JSON.parse(product.images);
                } catch (e) {
                    galleryList = [];
                }
            }

            if (Array.isArray(galleryList) && galleryList.length > 0) {
                badge.textContent = galleryList.length + ' image(s)';
                galleryList.forEach(imgUrl => {
                    const fname = imgUrl.split('/').pop();
                    const card = document.createElement('div');
                    card.className = 'flex items-center gap-3 p-2 bg-slate-50 rounded-xl border border-slate-200';
                    card.innerHTML = `
                        <img src="${imgUrl}" class="w-14 h-14 object-cover rounded-lg border border-slate-200 shadow-2xs shrink-0">
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-mono font-bold text-slate-700 truncate" title="${fname}">${fname}</p>
                            <div class="flex items-center gap-2 mt-1.5">
                                <button type="button" onclick="triggerReplaceImage('${product.id}', 'gallery', '${imgUrl}')" 
                                    class="px-2 py-0.5 text-[10px] font-bold bg-amber-100 hover:bg-amber-200 text-amber-900 rounded-md">
                                    🔄 Replace
                                </button>
                                <button type="button" onclick="confirmDeleteSingleImage('${product.id}', 'gallery', '${imgUrl}')" 
                                    class="px-2 py-0.5 text-[10px] font-bold bg-rose-50 hover:bg-rose-100 text-rose-700 rounded-md">
                                    🗑️ Delete
                                </button>
                            </div>
                        </div>
                    `;
                    galleryContainer.appendChild(card);
                });
            } else {
                badge.textContent = '0 images';
                galleryContainer.innerHTML = '<span class="text-xs text-slate-400 italic col-span-2 text-center py-2">No gallery images uploaded.</span>';
            }

            openModal('editModal');
        }
    </script>
<?php endif; ?>

<!-- Real-time Order / Inquiry Background Polling Notification Script -->
<script>
    setTimeout(() => {
        location.reload();
    }, 120000);

    (function () {
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

        setInterval(checkUpdates, 15000);
        checkUpdates();
    })();

    // ── PRODUCT STATUS TOGGLE ────────────────────────────────────────────────
    async function toggleProductStatus(productId, btn) {
        const currentActive = parseInt(btn.dataset.isActive, 10);
        const newActive = currentActive === 1 ? 0 : 1;

        const label = document.getElementById('status-label-' + productId);
        const dot = document.getElementById('status-dot-' + productId);
        const originalText = label.textContent;

        // Loading state
        btn.disabled = true;
        btn.style.opacity = '0.6';
        label.textContent = 'Saving...';

        try {
            const response = await fetch('products.php?action=toggle_status', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: productId, is_active: newActive })
            });

            const result = await response.json();

            if (response.ok && result.success) {
                // Update button appearance in-place — no page reload
                btn.dataset.isActive = String(newActive);

                if (newActive === 1) {
                    btn.className = btn.className
                        .replace('bg-slate-100 text-slate-500 border-slate-200 focus:ring-slate-400',
                            'bg-emerald-50 text-emerald-700 border-emerald-200 focus:ring-emerald-400');
                    dot.className = dot.className.replace('bg-slate-400', 'bg-emerald-500');
                    label.textContent = 'Active';
                } else {
                    btn.className = btn.className
                        .replace('bg-emerald-50 text-emerald-700 border-emerald-200 focus:ring-emerald-400',
                            'bg-slate-100 text-slate-500 border-slate-200 focus:ring-slate-400');
                    dot.className = dot.className.replace('bg-emerald-500', 'bg-slate-400');
                    label.textContent = 'Inactive';
                }

                // Brief success flash on the row
                const row = btn.closest('tr');
                if (row) {
                    row.style.transition = 'background 0.15s';
                    row.style.background = newActive === 1 ? '#f0fdf4' : '#f8fafc';
                    setTimeout(() => { row.style.background = ''; }, 1200);
                }
            } else {
                // Revert label on error
                label.textContent = originalText;
                alert('Error: ' + (result.message || 'Failed to update status.'));
            }
        } catch (err) {
            label.textContent = originalText;
            alert('Network error. Please check your connection and try again.');
            console.error('toggleProductStatus error:', err);
        } finally {
            btn.disabled = false;
            btn.style.opacity = '';
        }
    }

    // ── PRODUCT FEATURED TOGGLE ──────────────────────────────────────────────
    async function toggleProductFeatured(productId, btn) {
        const currentFeatured = parseInt(btn.dataset.isFeatured, 10);
        const newFeatured = currentFeatured === 1 ? 0 : 1;

        btn.disabled = true;
        btn.style.opacity = '0.6';

        try {
            const response = await fetch('products.php?action=toggle_featured', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: productId, is_featured: newFeatured })
            });

            const result = await response.json();

            if (response.ok && result.success) {
                btn.dataset.isFeatured = String(newFeatured);

                if (newFeatured === 1) {
                    btn.className = 'inline-flex items-center gap-1 px-2 py-0.5 rounded font-semibold border text-[11px] mb-1 transition-all cursor-pointer hover:opacity-80 bg-sky-50 text-sky-700 border-sky-200';
                } else {
                    btn.className = 'inline-flex items-center gap-1 px-2 py-0.5 rounded font-semibold border text-[11px] mb-1 transition-all cursor-pointer hover:opacity-80 bg-slate-50 text-slate-400 border-slate-200';
                }

                const row = btn.closest('tr');
                if (row) {
                    row.style.transition = 'background 0.15s';
                    row.style.background = newFeatured === 1 ? '#f0f9ff' : '#f8fafc';
                    setTimeout(() => { row.style.background = ''; }, 1200);
                }
            } else {
                alert('Error: ' + (result.message || 'Failed to update featured status.'));
            }
        } catch (err) {
            alert('Network error. Please check your connection and try again.');
            console.error('toggleProductFeatured error:', err);
        } finally {
            btn.disabled = false;
            btn.style.opacity = '';
        }
    }

    // ── PRODUCT BESTSELLER TOGGLE ─────────────────────────────────────────────
    async function toggleProductBestseller(productId, btn) {
        const currentBestseller = parseInt(btn.dataset.isBestseller, 10);
        const newBestseller = currentBestseller === 1 ? 0 : 1;

        btn.disabled = true;
        btn.style.opacity = '0.6';

        try {
            const response = await fetch('products.php?action=toggle_bestseller', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: productId, is_bestseller: newBestseller })
            });

            const result = await response.json();

            if (response.ok && result.success) {
                btn.dataset.isBestseller = String(newBestseller);

                if (newBestseller === 1) {
                    btn.className = 'inline-flex items-center gap-1 px-2 py-0.5 rounded font-semibold border text-[11px] transition-all cursor-pointer hover:opacity-80 bg-amber-50 text-amber-800 border-amber-200';
                } else {
                    btn.className = 'inline-flex items-center gap-1 px-2 py-0.5 rounded font-semibold border text-[11px] transition-all cursor-pointer hover:opacity-80 bg-slate-50 text-slate-400 border-slate-200';
                }

                const row = btn.closest('tr');
                if (row) {
                    row.style.transition = 'background 0.15s';
                    row.style.background = newBestseller === 1 ? '#fffbeb' : '#f8fafc';
                    setTimeout(() => { row.style.background = ''; }, 1200);
                }
            } else {
                alert('Error: ' + (result.message || 'Failed to update bestseller status.'));
            }
        } catch (err) {
            alert('Network error. Please check your connection and try again.');
            console.error('toggleProductBestseller error:', err);
        } finally {
            btn.disabled = false;
            btn.style.opacity = '';
        }
    }
</script>