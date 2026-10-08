<?php
/**
<<<<<<< HEAD
 * admin_subscriptions.php — Complete, Real, End-to-End Subscription Management System
 *
 * Sections:
 *   1. [ Overview ]    — Real statistics, breakdown by plan, recent subscriptions, upcoming deliveries
 *   2. [ Plans ]       — Subscription plans CRUD, reordering, duplicate, safe deactivation,
 *                        and image processing reusing the Product image pipeline.
 *   3. [ Subscribers ] — Complete customer subscriptions table with search, dynamic filters,
 *                        sorting, pagination, detail modal, edit modal, status management, and CSV export.
 *
 * Security & Reliability:
 *   - Admin session authentication required.
 *   - Prepared PDO statements everywhere.
 *   - Dual image storage (OMG_PRIMARY_DIR + OMG_SECONDARY_DIR) with 1000x1000 square GD crop.
 *   - Safe plan deletion protection (prevents deleting plans that have subscribers).
=======
 * admin_subscriptions.php — Subscription Plans Image Management (Admin Panel)
 *
 * Reuses the EXISTING Product image pipeline:
 *   - cropToSquare1000()
 *   - generateProductImageFilename()
 *   - handleFileUpload() (via reuse pattern)
 *   - deleteLocalImage()
 *   - OMG_PRIMARY_DIR / OMG_SECONDARY_DIR / OMG_IMG_URL_PATH
 *   - Admin session authentication
 *
 * DOES NOT create any new image-processing system.
>>>>>>> b34855a241af95ea619ff4cb20e5c1044d14eec8
 */

require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

<<<<<<< HEAD
if (!$db) {
    die("Database connection failed. Please check server configuration.");
}

$is_main_admin = ($_SESSION['admin_username'] ?? '') === 'main_admin';

// ── AUTO-MIGRATE: Ensure subscription_plans & subscriptions tables exist ────────
try {
    $db->exec("CREATE TABLE IF NOT EXISTS `subscription_plans` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(255) NOT NULL,
        `slug` VARCHAR(100) UNIQUE NOT NULL,
        `tagline` VARCHAR(255),
        `description` TEXT,
        `frequency` ENUM('monthly','quarterly','yearly') NOT NULL,
        `deliveries_per_year` INT NOT NULL DEFAULT 12,
        `price_per_delivery` DECIMAL(10,2) NOT NULL,
        `total_price` DECIMAL(10,2) NOT NULL,
        `savings_percent` INT DEFAULT 0,
        `features` LONGTEXT,
        `image` VARCHAR(255) DEFAULT NULL,
        `is_popular` TINYINT(1) DEFAULT 0,
        `is_active` TINYINT(1) DEFAULT 1,
        `display_order` INT DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Check if `image` column exists
    $cols = $db->query("SHOW COLUMNS FROM `subscription_plans`")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('image', $cols)) {
        $db->exec("ALTER TABLE `subscription_plans` ADD COLUMN `image` VARCHAR(255) DEFAULT NULL AFTER `features`");
    }

    // subscriptions table
    $db->exec("CREATE TABLE IF NOT EXISTS `subscriptions` (
        `id` CHAR(36) PRIMARY KEY,
        `user_id` CHAR(36) NOT NULL,
        `plan_id` INT NOT NULL,
        `occasion_type` VARCHAR(100) NOT NULL,
        `occasion_date` DATE NOT NULL,
        `recipient_name` VARCHAR(255),
        `recipient_phone` VARCHAR(30),
        `delivery_address` TEXT,
        `city` VARCHAR(100) DEFAULT 'Bangalore',
        `status` ENUM('active','paused','cancelled','expired') DEFAULT 'active',
        `next_delivery_date` DATE,
        `total_deliveries` INT DEFAULT 0,
        `notes` TEXT,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX (`user_id`),
        INDEX (`plan_id`),
        INDEX (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Seed plans if table is empty
    $planCount = (int)$db->query("SELECT COUNT(*) FROM subscription_plans")->fetchColumn();
    if ($planCount === 0) {
        $db->exec("INSERT INTO subscription_plans
            (name,slug,tagline,description,frequency,deliveries_per_year,price_per_delivery,total_price,savings_percent,features,image,is_popular,display_order)
            VALUES
            ('Monthly Bloom','monthly-bloom','Fresh joy, every month','Receive a curated luxury floral arrangement or hamper delivered to your loved one once a month, timed perfectly around your chosen occasion date.','monthly',12,999.00,11988.00,0,'[\"1 curated delivery per month\",\"Occasion-timed delivery\",\"Handpicked seasonal blooms\",\"Premium packaging & ribbon\",\"Digital occasion reminder\",\"Free delivery within Bangalore\"]','https://miaoda-site-img.s3cdn.medo.dev/images/KLing_8fb5dcf8-22bd-4fbd-98ba-1611bfcdcc4d.jpg',0,1),
            ('Quarterly Celebration','quarterly-celebration','Four grand moments a year','Let us surprise your loved one four times a year with an exclusive curated hamper or luxury arrangement for each season of your special bond.','quarterly',4,1799.00,7196.00,20,'[\"1 premium delivery every quarter\",\"Larger luxury arrangements\",\"Seasonal exclusive hampers\",\"Personalized message card\",\"Photo delivery confirmation\",\"Free priority delivery\"]','https://miaoda-site-img.s3cdn.medo.dev/images/KLing_3556e18d-69b0-4c22-93c1-29efba584217.jpg',1,2),
            ('Annual Romance','annual-romance','The grandest single gesture','One extraordinary, over-the-top floral creation or premium hamper set once a year on your most special occasion — crafted as a true masterpiece.','yearly',1,3999.00,3999.00,33,'[\"1 grand annual delivery\",\"Bespoke signature arrangement\",\"Complimentary add-on upgrade\",\"Dedicated florist consultation\",\"Premium keepsake packaging\",\"Express same-day delivery option\"]','https://miaoda-site-img.s3cdn.medo.dev/images/KLing_14558096-74be-4c1a-a8a2-e0334e6050d9.jpg',0,3)");
    }
} catch (Exception $e) {
    error_log("[OMG Subscription Admin] Auto-migrate error: " . $e->getMessage());
}

// ── REUSE PRODUCT IMAGE FUNCTIONS ───────────────────────────────────────────
=======
$is_main_admin = ($_SESSION['admin_username'] ?? '') === 'main_admin';

// ── AUTO-MIGRATE: Add `image` column to subscription_plans if missing ────────
try {
    $cols = $db->query("SHOW COLUMNS FROM `subscription_plans`")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('image', $cols)) {
        $db->exec("ALTER TABLE `subscription_plans` ADD COLUMN `image` VARCHAR(255) DEFAULT NULL AFTER `features`");
        error_log('[OMG Admin] Added `image` column to subscription_plans table.');
    }
} catch (Exception $e) {
    error_log('[OMG Admin] Could not add image column to subscription_plans: ' . $e->getMessage());
}

// ── REUSE PRODUCT IMAGE FUNCTIONS ───────────────────────────────────────────
// These functions are defined with if(!function_exists()) guards in admin_products.php.
// We define them here too under the same guard pattern so they work independently.

>>>>>>> b34855a241af95ea619ff4cb20e5c1044d14eec8
if (!function_exists('deleteLocalImage')) {
    function deleteLocalImage(string $imagePath): void
    {
        if (empty($imagePath)) return;
        $filename = basename($imagePath);
        if (empty($filename) || $filename === '.' || $filename === '..') return;

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
    function cropToSquare1000(string $tmpName, string $destPath): bool 
    {
        if (!extension_loaded('gd')) {
            return move_uploaded_file($tmpName, $destPath);
        }
        $info = getimagesize($tmpName);
        if (!$info) return false;
        [$srcW, $srcH, $imgType] = [$info[0], $info[1], $info[2]];
        switch ($imgType) {
            case IMAGETYPE_JPEG: $src = imagecreatefromjpeg($tmpName); break;
            case IMAGETYPE_PNG:  $src = imagecreatefrompng($tmpName);  break;
            case IMAGETYPE_GIF:  $src = imagecreatefromgif($tmpName);  break;
            case IMAGETYPE_WEBP: $src = imagecreatefromwebp($tmpName); break;
            default: return false;
        }
        if (!$src) return false;
        $squareSize = min($srcW, $srcH);
        $cropX = (int)(($srcW - $squareSize) / 2);
        $cropY = (int)(($srcH - $squareSize) / 2);
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
        return empty($text) ? 'subscription' : $text;
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
        if (empty($extension)) $extension = 'jpg';
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

<<<<<<< HEAD
=======
/**
 * handleSubscriptionImageUpload()
 *
 * Reuses the existing Product image pipeline for Subscription plan images.
 * Uses the same validation, cropToSquare1000, filename strategy, and
 * dual-storage (OMG_PRIMARY_DIR + OMG_SECONDARY_DIR / uploads cache).
 */
>>>>>>> b34855a241af95ea619ff4cb20e5c1044d14eec8
function handleSubscriptionImageUpload(string $fileKey, string $planName = '', string $existingUrl = ''): string
{
    if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
        return $existingUrl;
    }

    $file    = $_FILES[$fileKey];
    $size    = $file['size'];
    $tmpName = $file['tmp_name'];
    $origName = $file['name'];

<<<<<<< HEAD
=======
    // 5 MB limit (same as Products)
>>>>>>> b34855a241af95ea619ff4cb20e5c1044d14eec8
    if ($size > 5 * 1024 * 1024) {
        throw new Exception('File is too large. Maximum allowed size is 5 MB.');
    }

<<<<<<< HEAD
=======
    // MIME validation (same as Products)
>>>>>>> b34855a241af95ea619ff4cb20e5c1044d14eec8
    $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($tmpName);
    $allowedMimes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
    if (!in_array($mimeType, $allowedMimes, true)) {
        throw new Exception('Invalid file type. Only JPG, JPEG, PNG, and WEBP are allowed.');
    }

<<<<<<< HEAD
=======
    // Extension validation (same as Products)
>>>>>>> b34855a241af95ea619ff4cb20e5c1044d14eec8
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
    if (!in_array($ext, $allowedExts, true)) {
        throw new Exception("Invalid file extension ('.$ext'). Only .jpg, .jpeg, .png, and .webp are permitted.");
    }

    $primaryDir   = OMG_PRIMARY_DIR;
    $secondaryDir = OMG_SECONDARY_DIR;

    if (!is_dir($primaryDir) && !mkdir($primaryDir, 0755, true)) {
<<<<<<< HEAD
        throw new Exception('Permanent image directory could not be created.');
    }
    if (!is_writable($primaryDir)) {
        throw new Exception('Permanent image directory is not writable.');
=======
        throw new Exception('Permanent image directory could not be created. Check server permissions.');
    }
    if (!is_writable($primaryDir)) {
        throw new Exception('Permanent image directory is not writable. Check server permissions.');
>>>>>>> b34855a241af95ea619ff4cb20e5c1044d14eec8
    }
    if (!is_dir($secondaryDir)) {
        @mkdir($secondaryDir, 0755, true);
    }

<<<<<<< HEAD
=======
    // Slug-based filename (same as Products)
>>>>>>> b34855a241af95ea619ff4cb20e5c1044d14eec8
    $targetFilename = generateProductImageFilename(
        !empty($planName) ? $planName : 'subscription',
        'main',
        'jpg'
    );

    $primaryTarget   = $primaryDir   . $targetFilename;
    $secondaryTarget = $secondaryDir . $targetFilename;

<<<<<<< HEAD
    if (!cropToSquare1000($tmpName, $primaryTarget)) {
        throw new Exception('Failed to crop and save the uploaded image.');
    }

    if (is_writable($secondaryDir) && !copy($primaryTarget, $secondaryTarget)) {
        error_log('[OMG Upload] copy() to backend/uploads/ failed: ' . $targetFilename);
    }

=======
    // Crop and save (same as Products)
    if (!cropToSquare1000($tmpName, $primaryTarget)) {
        throw new Exception('Failed to process and save the uploaded image.');
    }

    // Cache copy (same as Products)
    if (is_writable($secondaryDir) && !copy($primaryTarget, $secondaryTarget)) {
        error_log('[OMG Upload] copy() to backend/uploads/ failed for subscription image: ' . $targetFilename);
    }

    // Delete old image (only if it was a locally stored image, not a CDN URL)
>>>>>>> b34855a241af95ea619ff4cb20e5c1044d14eec8
    if (!empty($existingUrl) && strpos($existingUrl, OMG_IMG_URL_PATH) !== false) {
        $oldFilename = basename($existingUrl);
        if ($oldFilename !== $targetFilename) {
            deleteLocalImage($existingUrl);
        }
    }

<<<<<<< HEAD
=======
    // Build URL (same pattern as Products using OMG_IMG_URL_PATH)
>>>>>>> b34855a241af95ea619ff4cb20e5c1044d14eec8
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    return $protocol . '://' . $_SERVER['HTTP_HOST'] . OMG_IMG_URL_PATH . $targetFilename;
}

$message = '';
$error   = '';

<<<<<<< HEAD
// ── EXPORT CSV ACTION (Streams real subscriber data) ─────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'export_csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="omg_subscribers_' . date('Y-m-d_His') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    // Header row
    fputcsv($out, [
        'Subscription ID',
        'Customer Name',
        'Customer Email',
        'Customer Phone',
        'Plan Name',
        'Frequency',
        'Occasion Type',
        'Occasion Date',
        'Recipient Name',
        'Recipient Phone',
        'Delivery Address',
        'City',
        'Status',
        'Next Delivery Date',
        'Total Deliveries',
        'Notes',
        'Created At',
        'Updated At'
    ]);

    $sql = "SELECT 
                s.id,
                COALESCE(up.name, s.recipient_name, 'Customer') AS customer_name,
                u.email AS customer_email,
                COALESCE(up.phone, s.recipient_phone, '') AS customer_phone,
                sp.name AS plan_name,
                sp.frequency AS plan_frequency,
                s.occasion_type,
                s.occasion_date,
                s.recipient_name,
                s.recipient_phone,
                s.delivery_address,
                s.city,
                s.status,
                s.next_delivery_date,
                s.total_deliveries,
                s.notes,
                s.created_at,
                s.updated_at
            FROM subscriptions s
            LEFT JOIN users u ON s.user_id = u.id
            LEFT JOIN user_profiles up ON s.user_id = up.id
            LEFT JOIN subscription_plans sp ON s.plan_id = sp.id
            ORDER BY s.created_at DESC";

    $stmt = $db->query($sql);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($out, [
            $row['id'],
            $row['customer_name'],
            $row['customer_email'],
            $row['customer_phone'],
            $row['plan_name'] ?? 'Custom Plan',
            ucfirst($row['plan_frequency'] ?? ''),
            $row['occasion_type'],
            $row['occasion_date'],
            $row['recipient_name'],
            $row['recipient_phone'],
            str_replace(["\r", "\n"], ' ', $row['delivery_address'] ?? ''),
            $row['city'],
            strtoupper($row['status']),
            $row['next_delivery_date'],
            $row['total_deliveries'],
            str_replace(["\r", "\n"], ' ', $row['notes'] ?? ''),
            $row['created_at'],
            $row['updated_at']
        ]);
    }
    fclose($out);
    exit();
}

// ── HANDLE POST ACTIONS ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    try {
        // 1. SAVE NEW PLAN
        if ($action === 'create_plan') {
            $name               = trim($_POST['name'] ?? '');
            $slug               = trim($_POST['slug'] ?? '');
            $tagline            = trim($_POST['tagline'] ?? '');
            $description        = trim($_POST['description'] ?? '');
            $frequency          = trim($_POST['frequency'] ?? 'monthly');
            $deliveries_per_yr  = (int)($_POST['deliveries_per_year'] ?? 12);
            $price_per_delivery = (float)($_POST['price_per_delivery'] ?? 0);
            $total_price        = (float)($_POST['total_price'] ?? 0);
            $savings_percent    = (int)($_POST['savings_percent'] ?? 0);
            $features_raw       = trim($_POST['features'] ?? '');
            $is_popular         = isset($_POST['is_popular']) ? 1 : 0;
            $is_active          = isset($_POST['is_active']) ? 1 : 0;
            $display_order      = (int)($_POST['display_order'] ?? 1);
            $image_url_fallback = trim($_POST['image_url'] ?? '');

            if (empty($name)) {
                throw new Exception('Plan Name is required.');
            }
            if ($price_per_delivery <= 0) {
                throw new Exception('Price per delivery must be greater than 0.');
            }
            if (empty($slug)) {
                $slug = slugify($name);
            } else {
                $slug = slugify($slug);
            }

            // Ensure unique slug
            $chk = $db->prepare("SELECT COUNT(*) FROM subscription_plans WHERE slug = ?");
            $chk->execute([$slug]);
            if ((int)$chk->fetchColumn() > 0) {
                $slug = $slug . '-' . time();
            }

            // Calculate total price if not provided
            if ($total_price <= 0) {
                $total_price = $price_per_delivery * $deliveries_per_yr;
            }

            // Format features JSON
            $featureLines = array_values(array_filter(array_map('trim', explode("\n", $features_raw))));
            $features_json = json_encode($featureLines);

            // Handle image upload
            $image = $image_url_fallback;
            if (isset($_FILES['plan_image']) && $_FILES['plan_image']['error'] === UPLOAD_ERR_OK) {
                $image = handleSubscriptionImageUpload('plan_image', $name, '');
            }

            $stmt = $db->prepare("INSERT INTO subscription_plans 
                (name, slug, tagline, description, frequency, deliveries_per_year, price_per_delivery, total_price, savings_percent, features, image, is_popular, is_active, display_order)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $name, $slug, $tagline, $description, $frequency, $deliveries_per_yr,
                $price_per_delivery, $total_price, $savings_percent, $features_json,
                $image, $is_popular, $is_active, $display_order
            ]);

            $message = "Subscription Plan '{$name}' created successfully!";
        }

        // 2. EDIT PLAN DETAILS
        elseif ($action === 'edit_plan') {
            $id                 = (int)($_POST['plan_id'] ?? 0);
            $name               = trim($_POST['name'] ?? '');
            $slug               = trim($_POST['slug'] ?? '');
            $tagline            = trim($_POST['tagline'] ?? '');
            $description        = trim($_POST['description'] ?? '');
            $frequency          = trim($_POST['frequency'] ?? 'monthly');
            $deliveries_per_yr  = (int)($_POST['deliveries_per_year'] ?? 12);
            $price_per_delivery = (float)($_POST['price_per_delivery'] ?? 0);
            $total_price        = (float)($_POST['total_price'] ?? 0);
            $savings_percent    = (int)($_POST['savings_percent'] ?? 0);
            $features_raw       = trim($_POST['features'] ?? '');
            $is_popular         = isset($_POST['is_popular']) ? 1 : 0;
            $is_active          = isset($_POST['is_active']) ? 1 : 0;
            $display_order      = (int)($_POST['display_order'] ?? 1);

            if (!$id || empty($name)) {
                throw new Exception('Plan ID and Name are required.');
            }
            if (empty($slug)) {
                $slug = slugify($name);
            }

            // Ensure unique slug (excluding this ID)
            $chk = $db->prepare("SELECT COUNT(*) FROM subscription_plans WHERE slug = ? AND id != ?");
            $chk->execute([$slug, $id]);
            if ((int)$chk->fetchColumn() > 0) {
                $slug = $slug . '-' . time();
            }

            if ($total_price <= 0) {
                $total_price = $price_per_delivery * $deliveries_per_yr;
            }

            $featureLines = array_values(array_filter(array_map('trim', explode("\n", $features_raw))));
            $features_json = json_encode($featureLines);

            $stmt = $db->prepare("UPDATE subscription_plans SET 
                name = ?, slug = ?, tagline = ?, description = ?, frequency = ?, 
                deliveries_per_year = ?, price_per_delivery = ?, total_price = ?, 
                savings_percent = ?, features = ?, is_popular = ?, is_active = ?, display_order = ?
                WHERE id = ?");
            $stmt->execute([
                $name, $slug, $tagline, $description, $frequency,
                $deliveries_per_yr, $price_per_delivery, $total_price,
                $savings_percent, $features_json, $is_popular, $is_active,
                $display_order, $id
            ]);

            $message = "Plan '{$name}' updated successfully!";
        }

        // 3. UPDATE PLAN IMAGE (Dedicated Modal)
        elseif ($action === 'update_plan_image') {
            $planId = (int)($_POST['plan_id'] ?? 0);
            if (!$planId) throw new Exception("Plan ID is required.");

            $curr = $db->prepare("SELECT name, image FROM subscription_plans WHERE id = ?");
            $curr->execute([$planId]);
            $plan = $curr->fetch(PDO::FETCH_ASSOC);
            if (!$plan) throw new Exception("Plan not found.");

            $existingImage = $plan['image'] ?? '';
            $remove_image = isset($_POST['remove_image']) && $_POST['remove_image'] === '1';

            if ($remove_image) {
                if (!empty($existingImage) && strpos($existingImage, OMG_IMG_URL_PATH) !== false) {
                    deleteLocalImage($existingImage);
                }
                $newImage = '';
            } elseif (isset($_FILES['plan_image']) && $_FILES['plan_image']['error'] === UPLOAD_ERR_OK) {
                $newImage = handleSubscriptionImageUpload('plan_image', $plan['name'], $existingImage);
            } elseif (!empty($_POST['image_url'])) {
                $newImage = trim($_POST['image_url']);
            } else {
                $newImage = $existingImage;
            }

            $stmt = $db->prepare("UPDATE subscription_plans SET image = ? WHERE id = ?");
            $stmt->execute([$newImage, $planId]);
            $message = "Plan image updated successfully!";
        }

        // 4. DUPLICATE PLAN
        elseif ($action === 'duplicate_plan') {
            $planId = (int)($_POST['plan_id'] ?? 0);
            $stmt = $db->prepare("SELECT * FROM subscription_plans WHERE id = ?");
            $stmt->execute([$planId]);
            $src = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$src) throw new Exception("Source plan not found.");

            $newName = $src['name'] . ' (Copy)';
            $newSlug = slugify($src['slug'] . '-copy-' . time());
            $maxOrder = (int)$db->query("SELECT MAX(display_order) FROM subscription_plans")->fetchColumn();

            $ins = $db->prepare("INSERT INTO subscription_plans 
                (name, slug, tagline, description, frequency, deliveries_per_year, price_per_delivery, total_price, savings_percent, features, image, is_popular, is_active, display_order)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, ?)");
            $ins->execute([
                $newName, $newSlug, $src['tagline'], $src['description'],
                $src['frequency'], $src['deliveries_per_year'], $src['price_per_delivery'],
                $src['total_price'], $src['savings_percent'], $src['features'],
                $src['image'], $maxOrder + 1
            ]);

            $message = "Plan duplicated as '{$newName}' (created as Draft/Inactive).";
        }

        // 5. TOGGLE PLAN ACTIVE STATUS
        elseif ($action === 'toggle_plan_active') {
            $planId = (int)($_POST['plan_id'] ?? 0);
            $stmt = $db->prepare("UPDATE subscription_plans SET is_active = IF(is_active = 1, 0, 1) WHERE id = ?");
            $stmt->execute([$planId]);
            $message = "Plan active status toggled.";
        }

        // 6. TOGGLE PLAN POPULAR BADGE
        elseif ($action === 'toggle_plan_popular') {
            $planId = (int)($_POST['plan_id'] ?? 0);
            $stmt = $db->prepare("UPDATE subscription_plans SET is_popular = IF(is_popular = 1, 0, 1) WHERE id = ?");
            $stmt->execute([$planId]);
            $message = "Popular badge status updated.";
        }

        // 7. MOVE PLAN DISPLAY ORDER (UP / DOWN)
        elseif ($action === 'reorder_plan') {
            $planId    = (int)($_POST['plan_id'] ?? 0);
            $direction = $_POST['direction'] ?? 'up';

            $currStmt = $db->prepare("SELECT id, display_order FROM subscription_plans WHERE id = ?");
            $currStmt->execute([$planId]);
            $curr = $currStmt->fetch(PDO::FETCH_ASSOC);

            if ($curr) {
                $curOrder = (int)$curr['display_order'];
                if ($direction === 'up') {
                    $otherStmt = $db->prepare("SELECT id, display_order FROM subscription_plans WHERE display_order < ? ORDER BY display_order DESC LIMIT 1");
                } else {
                    $otherStmt = $db->prepare("SELECT id, display_order FROM subscription_plans WHERE display_order > ? ORDER BY display_order ASC LIMIT 1");
                }
                $otherStmt->execute([$curOrder]);
                $other = $otherStmt->fetch(PDO::FETCH_ASSOC);

                if ($other) {
                    $db->beginTransaction();
                    $db->prepare("UPDATE subscription_plans SET display_order = ? WHERE id = ?")->execute([$other['display_order'], $curr['id']]);
                    $db->prepare("UPDATE subscription_plans SET display_order = ? WHERE id = ?")->execute([$curOrder, $other['id']]);
                    $db->commit();
                    $message = "Display order updated.";
                }
            }
        }

        // 8. SAFE DELETE PLAN
        elseif ($action === 'delete_plan') {
            $planId = (int)($_POST['plan_id'] ?? 0);
            
            // Check if plan has subscribers
            $subCountStmt = $db->prepare("SELECT COUNT(*) FROM subscriptions WHERE plan_id = ?");
            $subCountStmt->execute([$planId]);
            $subCount = (int)$subCountStmt->fetchColumn();

            if ($subCount > 0) {
                // Do not delete! Protect subscriber history.
                throw new Exception("Cannot delete this plan because it has {$subCount} subscriber(s). Please deactivate it instead to prevent new subscriptions while keeping existing records.");
            }

            // Safe to delete since 0 subscribers
            $stmtImg = $db->prepare("SELECT image FROM subscription_plans WHERE id = ?");
            $stmtImg->execute([$planId]);
            $oldImg = $stmtImg->fetchColumn();
            if ($oldImg && strpos($oldImg, OMG_IMG_URL_PATH) !== false) {
                deleteLocalImage($oldImg);
            }

            $db->prepare("DELETE FROM subscription_plans WHERE id = ?")->execute([$planId]);
            $message = "Plan deleted successfully.";
        }

        // 9. UPDATE SUBSCRIBER STATUS (Pause, Resume, Cancel, Expire)
        elseif ($action === 'update_subscriber_status') {
            $subId     = trim($_POST['subscription_id'] ?? '');
            $newStatus = trim($_POST['status'] ?? '');
            $allowedStatuses = ['active', 'paused', 'cancelled', 'expired'];

            if (!$subId || !in_array($newStatus, $allowedStatuses, true)) {
                throw new Exception("Invalid subscription ID or status.");
            }

            $stmt = $db->prepare("UPDATE subscriptions SET status = ? WHERE id = ?");
            $stmt->execute([$newStatus, $subId]);

            $message = "Subscription status changed to '" . strtoupper($newStatus) . "'.";
        }

        // 10. EDIT SUBSCRIBER DETAILS
        elseif ($action === 'edit_subscriber') {
            $subId          = trim($_POST['subscription_id'] ?? '');
            $planId         = (int)($_POST['plan_id'] ?? 0);
            $occasionType   = trim($_POST['occasion_type'] ?? '');
            $occasionDate   = trim($_POST['occasion_date'] ?? '');
            $recipientName  = trim($_POST['recipient_name'] ?? '');
            $recipientPhone = trim($_POST['recipient_phone'] ?? '');
            $deliveryAddr   = trim($_POST['delivery_address'] ?? '');
            $city           = trim($_POST['city'] ?? 'Bangalore');
            $status         = trim($_POST['status'] ?? 'active');
            $nextDelivery   = trim($_POST['next_delivery_date'] ?? '');
            $totalDeliv     = (int)($_POST['total_deliveries'] ?? 0);
            $notes          = trim($_POST['notes'] ?? '');

            if (!$subId || !$planId || !$occasionType || !$occasionDate) {
                throw new Exception("Subscription ID, Plan, Occasion Type, and Occasion Date are required.");
            }

            $stmt = $db->prepare("UPDATE subscriptions SET 
                plan_id = ?, occasion_type = ?, occasion_date = ?, 
                recipient_name = ?, recipient_phone = ?, delivery_address = ?, 
                city = ?, status = ?, next_delivery_date = ?, 
                total_deliveries = ?, notes = ?
                WHERE id = ?");
            $stmt->execute([
                $planId, $occasionType, $occasionDate,
                $recipientName, $recipientPhone, $deliveryAddr,
                $city, $status, !empty($nextDelivery) ? $nextDelivery : null,
                $totalDeliv, $notes, $subId
            ]);

            $message = "Subscriber details updated successfully!";
        }

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// ── DETERMINE ACTIVE SUB-TAB ────────────────────────────────────────────────
$currentSubTab = $_GET['sub'] ?? 'overview';
if (!in_array($currentSubTab, ['overview', 'plans', 'subscribers'], true)) {
    $currentSubTab = 'overview';
}

// ── QUERY 1: ALL PLANS WITH SUBSCRIBER COUNTS ──────────────────────────────
$plans = [];
try {
    $planQuery = "SELECT 
                    sp.*,
                    COUNT(s.id) AS subscriber_count,
                    SUM(CASE WHEN s.status = 'active' THEN 1 ELSE 0 END) AS active_subscribers,
                    SUM(CASE WHEN s.status = 'paused' THEN 1 ELSE 0 END) AS paused_subscribers,
                    SUM(CASE WHEN s.status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_subscribers
                  FROM subscription_plans sp
                  LEFT JOIN subscriptions s ON sp.id = s.plan_id
                  GROUP BY sp.id
                  ORDER BY sp.display_order ASC, sp.id ASC";
    $plans = $db->query($planQuery)->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("[OMG Admin] Load plans error: " . $e->getMessage());
}

// ── QUERY 2: REAL OVERVIEW STATISTICS ───────────────────────────────────────
$stats = [
    'total_subscribers' => 0,
    'active'            => 0,
    'paused'            => 0,
    'cancelled'         => 0,
    'expired'           => 0,
    'most_popular_plan' => 'N/A'
];

try {
    $stStmt = $db->query("SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active_cnt,
        SUM(CASE WHEN status = 'paused' THEN 1 ELSE 0 END) AS paused_cnt,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_cnt,
        SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) AS expired_cnt
        FROM subscriptions");
    $rawSt = $stStmt->fetch(PDO::FETCH_ASSOC);
    if ($rawSt) {
        $stats['total_subscribers'] = (int)($rawSt['total'] ?? 0);
        $stats['active']            = (int)($rawSt['active_cnt'] ?? 0);
        $stats['paused']            = (int)($rawSt['paused_cnt'] ?? 0);
        $stats['cancelled']         = (int)($rawSt['cancelled_cnt'] ?? 0);
        $stats['expired']           = (int)($rawSt['expired_cnt'] ?? 0);
    }

    // Most Subscribed Plan
    $popStmt = $db->query("SELECT sp.name, COUNT(s.id) as cnt 
                           FROM subscription_plans sp 
                           JOIN subscriptions s ON s.plan_id = sp.id 
                           GROUP BY sp.id 
                           ORDER BY cnt DESC LIMIT 1");
    $popPlan = $popStmt->fetch(PDO::FETCH_ASSOC);
    if ($popPlan) {
        $stats['most_popular_plan'] = $popPlan['name'] . ' (' . $popPlan['cnt'] . ')';
    }
} catch (Exception $e) {
    error_log("[OMG Admin] Overview stats error: " . $e->getMessage());
}

// ── QUERY 3: UPCOMING DELIVERIES (from subscriptions.next_delivery_date) ────
$upcomingDeliveries = [];
try {
    $upStmt = $db->query("SELECT 
            s.*,
            COALESCE(up.name, s.recipient_name, 'Customer') AS customer_name,
            u.email AS customer_email,
            COALESCE(up.phone, s.recipient_phone, '') AS customer_phone,
            sp.name AS plan_name,
            sp.image AS plan_image,
            sp.frequency AS plan_frequency
        FROM subscriptions s
        LEFT JOIN users u ON s.user_id = u.id
        LEFT JOIN user_profiles up ON s.user_id = up.id
        LEFT JOIN subscription_plans sp ON s.plan_id = sp.id
        WHERE s.next_delivery_date IS NOT NULL 
          AND s.status = 'active'
        ORDER BY s.next_delivery_date ASC
        LIMIT 6");
    $upcomingDeliveries = $upStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("[OMG Admin] Upcoming deliveries error: " . $e->getMessage());
}

// ── QUERY 4: RECENT SUBSCRIPTIONS (from subscriptions.created_at) ───────────
$recentSubscriptions = [];
try {
    $rcStmt = $db->query("SELECT 
            s.*,
            COALESCE(up.name, s.recipient_name, 'Customer') AS customer_name,
            u.email AS customer_email,
            COALESCE(up.phone, s.recipient_phone, '') AS customer_phone,
            sp.name AS plan_name,
            sp.image AS plan_image,
            sp.frequency AS plan_frequency
        FROM subscriptions s
        LEFT JOIN users u ON s.user_id = u.id
        LEFT JOIN user_profiles up ON s.user_id = up.id
        LEFT JOIN subscription_plans sp ON s.plan_id = sp.id
        ORDER BY s.created_at DESC
        LIMIT 6");
    $recentSubscriptions = $rcStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("[OMG Admin] Recent subscriptions error: " . $e->getMessage());
}

// ── QUERY 5: SUBSCRIBERS TABLE (Filters, Search, Sorting, Pagination) ───────
$subscribers = [];
$totalSubscriberRecords = 0;
$totalPages = 1;
$perPage = 15;
$currentPage = max(1, (int)($_GET['page'] ?? 1));

// Filter parameters
$searchFilter     = trim($_GET['search'] ?? '');
$statusFilter     = trim($_GET['status'] ?? '');
$planFilter       = (int)($_GET['plan_id'] ?? 0);
$frequencyFilter  = trim($_GET['frequency'] ?? '');
$occasionFilter   = trim($_GET['occasion_type'] ?? '');
$cityFilter       = trim($_GET['city'] ?? '');
$sortField        = trim($_GET['sort'] ?? 'newest');

if ($currentSubTab === 'subscribers') {
    try {
        $where = ["1=1"];
        $params = [];

        // Search in Name, Email, Phone, Recipient, Subscription ID
        if ($searchFilter !== '') {
            $where[] = "(
                u.email LIKE :search OR
                up.name LIKE :search OR
                up.phone LIKE :search OR
                s.recipient_name LIKE :search OR
                s.recipient_phone LIKE :search OR
                s.id LIKE :search OR
                s.delivery_address LIKE :search
            )";
            $params[':search'] = '%' . $searchFilter . '%';
        }

        // Status Filter
        if ($statusFilter !== '' && $statusFilter !== 'all') {
            $where[] = "s.status = :status";
            $params[':status'] = $statusFilter;
        }

        // Plan Filter
        if ($planFilter > 0) {
            $where[] = "s.plan_id = :plan_id";
            $params[':plan_id'] = $planFilter;
        }

        // Frequency Filter
        if ($frequencyFilter !== '' && $frequencyFilter !== 'all') {
            $where[] = "sp.frequency = :freq";
            $params[':freq'] = $frequencyFilter;
        }

        // Occasion Filter
        if ($occasionFilter !== '' && $occasionFilter !== 'all') {
            $where[] = "s.occasion_type = :occ";
            $params[':occ'] = $occasionFilter;
        }

        // City Filter
        if ($cityFilter !== '') {
            $where[] = "s.city LIKE :city";
            $params[':city'] = '%' . $cityFilter . '%';
        }

        $whereClause = implode(" AND ", $where);

        // Sorting
        $orderClause = "s.created_at DESC";
        switch ($sortField) {
            case 'oldest':
                $orderClause = "s.created_at ASC";
                break;
            case 'next_delivery':
                $orderClause = "s.next_delivery_date ASC, s.created_at DESC";
                break;
            case 'occasion_date':
                $orderClause = "s.occasion_date ASC";
                break;
            case 'customer_name':
                $orderClause = "COALESCE(up.name, s.recipient_name) ASC";
                break;
            case 'plan':
                $orderClause = "sp.name ASC";
                break;
            case 'status':
                $orderClause = "s.status ASC, s.created_at DESC";
                break;
            case 'newest':
            default:
                $orderClause = "s.created_at DESC";
                break;
        }

        // Count total
        $cntSql = "SELECT COUNT(*) FROM subscriptions s
                   LEFT JOIN users u ON s.user_id = u.id
                   LEFT JOIN user_profiles up ON s.user_id = up.id
                   LEFT JOIN subscription_plans sp ON s.plan_id = sp.id
                   WHERE {$whereClause}";
        $cntStmt = $db->prepare($cntSql);
        $cntStmt->execute($params);
        $totalSubscriberRecords = (int)$cntStmt->fetchColumn();

        $totalPages = max(1, (int)ceil($totalSubscriberRecords / $perPage));
        if ($currentPage > $totalPages) $currentPage = $totalPages;
        $offset = ($currentPage - 1) * $perPage;

        // Fetch paginated rows
        $listSql = "SELECT 
                s.*,
                COALESCE(up.name, s.recipient_name, 'Customer') AS customer_name,
                u.email AS customer_email,
                COALESCE(up.phone, s.recipient_phone, '') AS customer_phone,
                sp.name AS plan_name,
                sp.slug AS plan_slug,
                sp.frequency AS plan_frequency,
                sp.price_per_delivery AS plan_price_per_delivery,
                sp.total_price AS plan_total_price,
                sp.image AS plan_image
            FROM subscriptions s
            LEFT JOIN users u ON s.user_id = u.id
            LEFT JOIN user_profiles up ON s.user_id = up.id
            LEFT JOIN subscription_plans sp ON s.plan_id = sp.id
            WHERE {$whereClause}
            ORDER BY {$orderClause}
            LIMIT {$perPage} OFFSET {$offset}";

        $listStmt = $db->prepare($listSql);
        foreach ($params as $k => $v) {
            $listStmt->bindValue($k, $v);
        }
        $listStmt->execute();
        $subscribers = $listStmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (Exception $e) {
        error_log("[OMG Admin] Load subscribers error: " . $e->getMessage());
    }
}

// Helper: Distinct Occasion Types for Filter
$distinctOccasions = [];
try {
    $distinctOccasions = $db->query("SELECT DISTINCT occasion_type FROM subscriptions WHERE occasion_type IS NOT NULL AND occasion_type != '' ORDER BY occasion_type ASC")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

$pageTitle = "Subscriptions Management — OMG Floral Store";
require_once 'admin_header.php';
?>

<div class="space-y-6">
    <!-- Header Title & Action Buttons -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200 pb-5">
        <div>
            <div class="flex items-center gap-2">
                <span class="text-xs uppercase tracking-widest font-bold text-amber-600 bg-amber-100/70 border border-amber-300 px-2.5 py-0.5 rounded-full">
                    Admin Portal
                </span>
                <span class="text-xs text-slate-400">• Occasion Floral Deliveries</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-serif font-bold text-slate-900 mt-1">Subscription Management</h1>
            <p class="text-slate-500 text-xs sm:text-sm mt-0.5">Control recurring flower subscriptions, oversee upcoming deliveries, manage tiers and upload custom images.</p>
        </div>

        <div class="flex items-center gap-2.5 flex-wrap">
            <a href="admin.php?tab=subscriptions&sub=subscribers&action=export_csv" 
               class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-xs font-semibold shadow-xs transition-colors">
                📥 Export CSV
            </a>
            <button onclick="openAddPlanModal()" 
                    class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl gold-gradient text-slate-950 text-xs font-bold shadow-md hover:brightness-105 transition-all">
                ✨ + Add Subscription Plan
            </button>
        </div>
    </div>

    <!-- Alert Feedback Banners -->
    <?php if (!empty($message)): ?>
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-2xl text-sm font-medium flex items-center justify-between shadow-xs">
            <div class="flex items-center gap-2">
                <span class="text-emerald-600 font-bold">✓</span>
                <span><?php echo htmlspecialchars($message); ?></span>
            </div>
            <button type="button" onclick="this.parentElement.remove()" class="text-emerald-500 hover:text-emerald-700 text-xs font-bold">✕</button>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 rounded-2xl text-sm font-medium flex items-center justify-between shadow-xs">
            <div class="flex items-center gap-2">
                <span class="text-rose-600 font-bold">⚠</span>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
            <button type="button" onclick="this.parentElement.remove()" class="text-rose-500 hover:text-rose-700 text-xs font-bold">✕</button>
        </div>
    <?php endif; ?>

    <!-- Sub-Navigation Navigation Tabs (Overview | Plans | Subscribers) -->
    <div class="flex items-center gap-2 border-b border-slate-200 pb-0 overflow-x-auto scrollbar-hide">
        <a href="admin.php?tab=subscriptions&sub=overview"
           class="px-5 py-3 text-sm font-bold flex items-center gap-2 border-b-2 transition-all whitespace-nowrap <?php echo $currentSubTab === 'overview' ? 'border-amber-500 text-amber-700 bg-amber-50/50 rounded-t-xl' : 'border-transparent text-slate-500 hover:text-slate-800 hover:border-slate-300'; ?>">
            <span>📊</span> Overview
            <span class="text-xs px-2 py-0.5 rounded-full <?php echo $currentSubTab === 'overview' ? 'bg-amber-200/80 text-amber-900' : 'bg-slate-200 text-slate-600'; ?>">
                <?php echo $stats['total_subscribers']; ?>
            </span>
        </a>

        <a href="admin.php?tab=subscriptions&sub=plans"
           class="px-5 py-3 text-sm font-bold flex items-center gap-2 border-b-2 transition-all whitespace-nowrap <?php echo $currentSubTab === 'plans' ? 'border-amber-500 text-amber-700 bg-amber-50/50 rounded-t-xl' : 'border-transparent text-slate-500 hover:text-slate-800 hover:border-slate-300'; ?>">
            <span>🌸</span> Subscription Plans
            <span class="text-xs px-2 py-0.5 rounded-full <?php echo $currentSubTab === 'plans' ? 'bg-amber-200/80 text-amber-900' : 'bg-slate-200 text-slate-600'; ?>">
                <?php echo count($plans); ?>
            </span>
        </a>

        <a href="admin.php?tab=subscriptions&sub=subscribers"
           class="px-5 py-3 text-sm font-bold flex items-center gap-2 border-b-2 transition-all whitespace-nowrap <?php echo $currentSubTab === 'subscribers' ? 'border-amber-500 text-amber-700 bg-amber-50/50 rounded-t-xl' : 'border-transparent text-slate-500 hover:text-slate-800 hover:border-slate-300'; ?>">
            <span>👥</span> Subscribers
            <span class="text-xs px-2 py-0.5 rounded-full <?php echo $currentSubTab === 'subscribers' ? 'bg-amber-200/80 text-amber-900' : 'bg-slate-200 text-slate-600'; ?>">
                <?php echo $stats['total_subscribers']; ?>
            </span>
        </a>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════════ -->
    <!-- SUB-TAB 1: OVERVIEW SECTION                                               -->
    <!-- ═══════════════════════════════════════════════════════════════════════════ -->
    <?php if ($currentSubTab === 'overview'): ?>
        <div class="space-y-8">
            <!-- Real Metric Cards -->
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3.5">
                <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs">
                    <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Total Subscribers</p>
                    <p class="text-2xl font-serif font-bold text-slate-900 mt-1"><?php echo $stats['total_subscribers']; ?></p>
                    <p class="text-[10px] text-slate-400 mt-0.5">All customer records</p>
                </div>
                <div class="bg-white p-4 rounded-2xl border border-emerald-200/80 bg-emerald-50/20 shadow-xs">
                    <p class="text-[11px] font-bold text-emerald-700 uppercase tracking-wider">Active</p>
                    <p class="text-2xl font-serif font-bold text-emerald-600 mt-1"><?php echo $stats['active']; ?></p>
                    <p class="text-[10px] text-emerald-600/70 mt-0.5">Receiving deliveries</p>
                </div>
                <div class="bg-white p-4 rounded-2xl border border-amber-200/80 bg-amber-50/20 shadow-xs">
                    <p class="text-[11px] font-bold text-amber-700 uppercase tracking-wider">Paused</p>
                    <p class="text-2xl font-serif font-bold text-amber-600 mt-1"><?php echo $stats['paused']; ?></p>
                    <p class="text-[10px] text-amber-600/70 mt-0.5">Temporarily on hold</p>
                </div>
                <div class="bg-white p-4 rounded-2xl border border-rose-200/80 bg-rose-50/20 shadow-xs">
                    <p class="text-[11px] font-bold text-rose-700 uppercase tracking-wider">Cancelled</p>
                    <p class="text-2xl font-serif font-bold text-rose-600 mt-1"><?php echo $stats['cancelled']; ?></p>
                    <p class="text-[10px] text-rose-600/70 mt-0.5">Retained for history</p>
                </div>
                <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-xs">
                    <p class="text-[11px] font-bold text-slate-600 uppercase tracking-wider">Expired</p>
                    <p class="text-2xl font-serif font-bold text-slate-500 mt-1"><?php echo $stats['expired']; ?></p>
                    <p class="text-[10px] text-slate-400 mt-0.5">Term concluded</p>
                </div>
                <div class="bg-white p-4 rounded-2xl border border-amber-300/80 bg-gradient-to-br from-white to-amber-50/40 shadow-xs">
                    <p class="text-[11px] font-bold text-amber-800 uppercase tracking-wider truncate">Most Subscribed</p>
                    <p class="text-sm font-bold text-slate-900 mt-2 truncate" title="<?php echo htmlspecialchars($stats['most_popular_plan']); ?>">
                        <?php echo htmlspecialchars($stats['most_popular_plan']); ?>
                    </p>
                    <p class="text-[10px] text-amber-700 mt-0.5">Top performing tier</p>
                </div>
            </div>

            <!-- Subscription Counts by Plan (Interactive Summary Cards) -->
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div>
                        <h3 class="text-base font-serif font-bold text-slate-900">Subscription Breakdown by Plan</h3>
                        <p class="text-xs text-slate-500">Live breakdown of customer distribution across plans.</p>
                    </div>
                    <a href="admin.php?tab=subscriptions&sub=plans" class="text-xs font-bold text-amber-700 hover:text-amber-800">
                        Manage Plans →
                    </a>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <?php foreach ($plans as $p): ?>
                        <div class="border border-slate-200 rounded-xl p-4.5 bg-slate-50/40 hover:border-amber-400 transition-all flex flex-col justify-between space-y-3">
                            <div class="flex items-start gap-3">
                                <?php if (!empty($p['image'])): ?>
                                    <img src="<?php echo htmlspecialchars($p['image']); ?>" alt="<?php echo htmlspecialchars($p['name']); ?>"
                                         class="w-12 h-12 rounded-xl object-cover border border-slate-200 shrink-0">
                                <?php else: ?>
                                    <div class="w-12 h-12 rounded-xl bg-slate-200 flex items-center justify-center text-xs font-bold text-slate-400 shrink-0">
                                        OMG
                                    </div>
                                <?php endif; ?>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-1.5">
                                        <h4 class="font-bold text-slate-900 text-sm truncate"><?php echo htmlspecialchars($p['name']); ?></h4>
                                        <?php if ($p['is_popular']): ?>
                                            <span class="text-[9px] font-bold px-1.5 py-0.5 rounded bg-amber-100 text-amber-800 border border-amber-300">Popular</span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-xs text-slate-500 capitalize"><?php echo htmlspecialchars($p['frequency']); ?> • ₹<?php echo number_format($p['price_per_delivery']); ?>/deliv</p>
                                </div>
                            </div>

                            <div class="grid grid-cols-3 gap-2 text-center pt-2 border-t border-slate-200/70 text-xs">
                                <div>
                                    <span class="text-slate-400 block text-[10px]">Total</span>
                                    <strong class="text-slate-800 font-bold"><?php echo (int)($p['subscriber_count'] ?? 0); ?></strong>
                                </div>
                                <div>
                                    <span class="text-emerald-500 block text-[10px]">Active</span>
                                    <strong class="text-emerald-600 font-bold"><?php echo (int)($p['active_subscribers'] ?? 0); ?></strong>
                                </div>
                                <div>
                                    <span class="text-amber-500 block text-[10px]">Paused</span>
                                    <strong class="text-amber-600 font-bold"><?php echo (int)($p['paused_subscribers'] ?? 0); ?></strong>
                                </div>
                            </div>

                            <a href="admin.php?tab=subscriptions&sub=subscribers&plan_id=<?php echo $p['id']; ?>"
                               class="text-center text-xs font-semibold text-slate-700 bg-white border border-slate-200 hover:bg-slate-50 py-1.5 rounded-lg transition-colors">
                                View <?php echo (int)($p['subscriber_count'] ?? 0); ?> Subscribers →
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Two-Column Grid: Upcoming Deliveries & Recent Subscriptions -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Upcoming Deliveries -->
                <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <div class="flex items-center gap-2">
                            <span class="p-1.5 rounded-lg bg-emerald-100 text-emerald-800 text-xs">🚚</span>
                            <div>
                                <h3 class="text-base font-serif font-bold text-slate-900">Upcoming Deliveries</h3>
                                <p class="text-xs text-slate-500">Scheduled using customer occasion dates.</p>
                            </div>
                        </div>
                        <a href="admin.php?tab=subscriptions&sub=subscribers&sort=next_delivery" class="text-xs font-bold text-amber-700 hover:text-amber-800">
                            View All →
                        </a>
                    </div>

                    <?php if (empty($upcomingDeliveries)): ?>
                        <div class="text-center py-10 text-slate-400 italic text-sm">
                            No upcoming scheduled deliveries found in the database.
                        </div>
                    <?php else: ?>
                        <div class="space-y-2.5">
                            <?php foreach ($upcomingDeliveries as $ud): ?>
                                <div class="p-3.5 rounded-xl border border-slate-200/70 hover:border-amber-300 bg-slate-50/50 flex items-center justify-between gap-3 text-xs">
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2">
                                            <strong class="text-slate-900 font-bold"><?php echo htmlspecialchars($ud['customer_name']); ?></strong>
                                            <span class="text-[10px] text-slate-400">• For: <?php echo htmlspecialchars($ud['recipient_name'] ?: 'Self'); ?></span>
                                        </div>
                                        <p class="text-slate-500 text-[11px] truncate mt-0.5">
                                            <?php echo htmlspecialchars($ud['plan_name'] ?? 'Plan'); ?> — <?php echo htmlspecialchars($ud['occasion_type']); ?> (<?php echo htmlspecialchars($ud['city']); ?>)
                                        </p>
                                    </div>
                                    <div class="text-right shrink-0">
                                        <span class="font-mono font-bold text-amber-700 bg-amber-100/80 border border-amber-300 px-2 py-0.5 rounded text-[11px] block">
                                            <?php echo htmlspecialchars($ud['next_delivery_date']); ?>
                                        </span>
                                        <span class="text-[10px] text-emerald-600 font-semibold block mt-0.5">● Active</span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Recent Subscriptions -->
                <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <div class="flex items-center gap-2">
                            <span class="p-1.5 rounded-lg bg-amber-100 text-amber-800 text-xs">✨</span>
                            <div>
                                <h3 class="text-base font-serif font-bold text-slate-900">Recent Subscriptions</h3>
                                <p class="text-xs text-slate-500">Latest customer signups by creation date.</p>
                            </div>
                        </div>
                        <a href="admin.php?tab=subscriptions&sub=subscribers&sort=newest" class="text-xs font-bold text-amber-700 hover:text-amber-800">
                            View All →
                        </a>
                    </div>

                    <?php if (empty($recentSubscriptions)): ?>
                        <div class="text-center py-10 text-slate-400 italic text-sm">
                            No subscription records stored in database yet.
                        </div>
                    <?php else: ?>
                        <div class="space-y-2.5">
                            <?php foreach ($recentSubscriptions as $rs): ?>
                                <div class="p-3.5 rounded-xl border border-slate-200/70 hover:border-amber-300 bg-slate-50/50 flex items-center justify-between gap-3 text-xs">
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2">
                                            <strong class="text-slate-900 font-bold"><?php echo htmlspecialchars($rs['customer_name']); ?></strong>
                                            <span class="text-[10px] font-mono text-slate-400">#<?php echo substr($rs['id'], 0, 8); ?></span>
                                        </div>
                                        <p class="text-slate-500 text-[11px] truncate mt-0.5">
                                            <?php echo htmlspecialchars($rs['plan_name'] ?? 'Plan'); ?> (<?php echo htmlspecialchars($rs['occasion_type']); ?>)
                                        </p>
                                    </div>
                                    <div class="text-right shrink-0">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase <?php 
                                            echo $rs['status'] === 'active' ? 'bg-emerald-100 text-emerald-800' :
                                                ($rs['status'] === 'paused' ? 'bg-amber-100 text-amber-800' : 'bg-slate-200 text-slate-700'); 
                                        ?>">
                                            <?php echo htmlspecialchars($rs['status']); ?>
                                        </span>
                                        <span class="text-[10px] text-slate-400 block mt-0.5">
                                            <?php echo date('d M Y', strtotime($rs['created_at'])); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>


    <!-- ═══════════════════════════════════════════════════════════════════════════ -->
    <!-- SUB-TAB 2: PLANS SECTION                                                  -->
    <!-- ═══════════════════════════════════════════════════════════════════════════ -->
    <?php if ($currentSubTab === 'plans'): ?>
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-4">
                <div>
                    <h3 class="text-lg font-serif font-bold text-slate-900">Subscription Plans (<?php echo count($plans); ?>)</h3>
                    <p class="text-xs text-slate-500">Live tiers reflected on the customer frontend. Drag/order plans, edit pricing, or replace 1000×1000 square cover images.</p>
                </div>
                <button onclick="openAddPlanModal()" 
                        class="px-4 py-2 rounded-xl gold-gradient text-slate-950 text-xs font-bold shadow-md hover:brightness-105 self-start sm:self-auto">
                    + Add New Plan
                </button>
            </div>

            <?php if (empty($plans)): ?>
                <div class="text-center py-12 text-slate-400 italic">No subscription plans found. Click "+ Add New Plan" to create one.</div>
            <?php else: ?>
                <div class="table-wrapper">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-100/70 text-slate-600 uppercase text-[11px] font-bold tracking-wider border-b border-slate-200">
                            <tr>
                                <th class="py-3 px-3 w-16">Order</th>
                                <th class="py-3 px-3 w-20">Image</th>
                                <th class="py-3 px-4">Plan Name & Slug</th>
                                <th class="py-3 px-3">Frequency</th>
                                <th class="py-3 px-3">Deliveries</th>
                                <th class="py-3 px-3">Price / Deliv</th>
                                <th class="py-3 px-3">Total Price</th>
                                <th class="py-3 px-3">Savings</th>
                                <th class="py-3 px-3">Subscribers</th>
                                <th class="py-3 px-3">Status</th>
                                <th class="py-3 px-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($plans as $p): ?>
                                <tr class="hover:bg-slate-50/80 transition-colors">
                                    <!-- Order & Up/Down -->
                                    <td class="py-3.5 px-3">
                                        <div class="flex items-center gap-1">
                                            <span class="font-mono text-xs font-bold text-slate-700 bg-slate-100 px-1.5 py-0.5 rounded">
                                                #<?php echo $p['display_order']; ?>
                                            </span>
                                            <div class="flex flex-col">
                                                <form method="POST" class="inline">
                                                    <input type="hidden" name="action" value="reorder_plan">
                                                    <input type="hidden" name="plan_id" value="<?php echo $p['id']; ?>">
                                                    <input type="hidden" name="direction" value="up">
                                                    <button type="submit" title="Move Up" class="text-[10px] text-slate-400 hover:text-amber-600 leading-none">▲</button>
                                                </form>
                                                <form method="POST" class="inline">
                                                    <input type="hidden" name="action" value="reorder_plan">
                                                    <input type="hidden" name="plan_id" value="<?php echo $p['id']; ?>">
                                                    <input type="hidden" name="direction" value="down">
                                                    <button type="submit" title="Move Down" class="text-[10px] text-slate-400 hover:text-amber-600 leading-none">▼</button>
                                                </form>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Image Thumbnail -->
                                    <td class="py-3.5 px-3">
                                        <div class="relative group w-14 h-14 rounded-xl overflow-hidden border border-slate-200 shadow-xs cursor-pointer"
                                             onclick='openPlanImageModal(<?php echo htmlspecialchars(json_encode($p), ENT_QUOTES); ?>)'>
                                            <?php if (!empty($p['image'])): ?>
                                                <img src="<?php echo htmlspecialchars($p['image']); ?>" alt="Plan Cover" class="w-full h-full object-cover">
                                            <?php else: ?>
                                                <div class="w-full h-full bg-slate-100 flex items-center justify-center text-[10px] text-slate-400 text-center leading-tight">
                                                    No<br>Image
                                                </div>
                                            <?php endif; ?>
                                            <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 flex items-center justify-center transition-opacity text-[10px] text-white font-bold">
                                                Edit
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Plan Name & Slug -->
                                    <td class="py-3.5 px-4 min-w-[180px]">
                                        <div class="flex items-center gap-1.5">
                                            <strong class="text-slate-900 font-bold"><?php echo htmlspecialchars($p['name']); ?></strong>
                                            <?php if ($p['is_popular']): ?>
                                                <span class="text-[9px] font-bold px-1.5 py-0.5 rounded bg-amber-100 text-amber-800 border border-amber-300">Popular</span>
                                            <?php endif; ?>
                                        </div>
                                        <p class="text-xs text-slate-500 italic truncate max-w-xs"><?php echo htmlspecialchars($p['tagline'] ?? ''); ?></p>
                                        <span class="font-mono text-[10px] text-slate-400">slug: <?php echo htmlspecialchars($p['slug']); ?></span>
                                    </td>

                                    <!-- Frequency -->
                                    <td class="py-3.5 px-3 text-xs font-semibold capitalize text-slate-700">
                                        <?php echo htmlspecialchars($p['frequency']); ?>
                                    </td>

                                    <!-- Deliveries/Yr -->
                                    <td class="py-3.5 px-3 text-xs font-mono text-slate-700">
                                        <?php echo $p['deliveries_per_year']; ?>/yr
                                    </td>

                                    <!-- Price/Deliv -->
                                    <td class="py-3.5 px-3 font-bold text-amber-600 text-xs">
                                        ₹<?php echo number_format($p['price_per_delivery']); ?>
                                    </td>

                                    <!-- Total Price -->
                                    <td class="py-3.5 px-3 font-bold text-slate-900 text-xs">
                                        ₹<?php echo number_format($p['total_price']); ?>
                                    </td>

                                    <!-- Savings -->
                                    <td class="py-3.5 px-3 text-xs">
                                        <?php if ($p['savings_percent'] > 0): ?>
                                            <span class="text-emerald-700 bg-emerald-100 font-bold px-1.5 py-0.5 rounded text-[10px]">
                                                <?php echo $p['savings_percent']; ?>% OFF
                                            </span>
                                        <?php else: ?>
                                            <span class="text-slate-400 text-[11px]">—</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Subscriber count with filter link -->
                                    <td class="py-3.5 px-3 text-xs">
                                        <a href="admin.php?tab=subscriptions&sub=subscribers&plan_id=<?php echo $p['id']; ?>"
                                           class="inline-flex items-center gap-1 font-bold text-slate-800 hover:text-amber-700 bg-slate-100 hover:bg-amber-100/50 px-2 py-1 rounded-lg transition-colors"
                                           title="Filter subscribers by this plan">
                                            👥 <?php echo (int)($p['subscriber_count'] ?? 0); ?>
                                        </a>
                                    </td>

                                    <!-- Status -->
                                    <td class="py-3.5 px-3 text-xs">
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="action" value="toggle_plan_active">
                                            <input type="hidden" name="plan_id" value="<?php echo $p['id']; ?>">
                                            <button type="submit" class="cursor-pointer font-semibold <?php echo $p['is_active'] ? 'text-emerald-600 hover:text-emerald-800' : 'text-slate-400 hover:text-slate-600'; ?>">
                                                <?php echo $p['is_active'] ? '● Active' : '○ Disabled'; ?>
                                            </button>
                                        </form>
                                    </td>

                                    <!-- Actions Menu -->
                                    <td class="py-3.5 px-4 text-right">
                                        <div class="flex items-center justify-end gap-1.5 flex-wrap">
                                            <!-- View Details Modal Trigger -->
                                            <button onclick='openViewPlanModal(<?php echo htmlspecialchars(json_encode($p), ENT_QUOTES); ?>)'
                                                    class="px-2.5 py-1 text-xs font-semibold rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700">
                                                View
                                            </button>

                                            <!-- Edit Plan Modal Trigger -->
                                            <button onclick='openEditPlanModal(<?php echo htmlspecialchars(json_encode($p), ENT_QUOTES); ?>)'
                                                    class="px-2.5 py-1 text-xs font-semibold rounded-lg bg-slate-800 hover:bg-slate-900 text-white">
                                                Edit
                                            </button>

                                            <!-- Edit Image Modal Trigger -->
                                            <button onclick='openPlanImageModal(<?php echo htmlspecialchars(json_encode($p), ENT_QUOTES); ?>)'
                                                    class="px-2.5 py-1 text-xs font-semibold rounded-lg bg-amber-100 hover:bg-amber-200 text-amber-900 border border-amber-300">
                                                Image
                                            </button>

                                            <!-- Duplicate Plan Trigger -->
                                            <form method="POST" onsubmit="return confirm('Duplicate plan as a new draft?');" class="inline">
                                                <input type="hidden" name="action" value="duplicate_plan">
                                                <input type="hidden" name="plan_id" value="<?php echo $p['id']; ?>">
                                                <button type="submit" class="px-2 py-1 text-xs rounded-lg text-slate-600 hover:bg-slate-100" title="Duplicate Plan">
                                                    📋
                                                </button>
                                            </form>

                                            <!-- Safe Delete / Deactivate -->
                                            <form method="POST" onsubmit="return confirm('Are you sure you want to delete or deactivate this plan? If subscribers exist, deletion will be blocked safely.');" class="inline">
                                                <input type="hidden" name="action" value="delete_plan">
                                                <input type="hidden" name="plan_id" value="<?php echo $p['id']; ?>">
                                                <button type="submit" class="px-2 py-1 text-xs rounded-lg text-rose-600 hover:bg-rose-50" title="Delete Plan">
                                                    🗑️
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>


    <!-- ═══════════════════════════════════════════════════════════════════════════ -->
    <!-- SUB-TAB 3: SUBSCRIBERS SECTION                                            -->
    <!-- ═══════════════════════════════════════════════════════════════════════════ -->
    <?php if ($currentSubTab === 'subscribers'): ?>
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-6 space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-100 pb-4">
                <div>
                    <h3 class="text-lg font-serif font-bold text-slate-900">
                        Customer Subscribers (<?php echo $totalSubscriberRecords; ?>)
                    </h3>
                    <p class="text-xs text-slate-500">Every active, paused, or cancelled subscription stored in the MySQL database.</p>
                </div>
                <div class="flex items-center gap-2">
                    <a href="admin.php?tab=subscriptions&sub=subscribers&action=export_csv" 
                       class="px-3.5 py-1.5 rounded-xl border border-slate-300 text-slate-700 hover:bg-slate-50 text-xs font-semibold">
                        📥 Export All CSV
                    </a>
                </div>
            </div>

            <!-- Server-Side Filters & Search Form -->
            <form method="GET" action="admin.php" class="bg-slate-50/70 p-4 rounded-xl border border-slate-200/80 space-y-3">
                <input type="hidden" name="tab" value="subscriptions">
                <input type="hidden" name="sub" value="subscribers">

                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">
                    <!-- Search Input -->
                    <div class="sm:col-span-2">
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-600 mb-1">Search</label>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($searchFilter); ?>" 
                               placeholder="Customer, Email, Phone, Recipient, ID..."
                               class="w-full px-3 py-1.5 rounded-lg border border-slate-300 text-xs outline-none focus:ring-2 focus:ring-amber-500 bg-white">
                    </div>

                    <!-- Status Filter -->
                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-600 mb-1">Status</label>
                        <select name="status" class="w-full px-3 py-1.5 rounded-lg border border-slate-300 text-xs outline-none focus:ring-2 focus:ring-amber-500 bg-white">
                            <option value="all" <?php echo $statusFilter === 'all' || $statusFilter === '' ? 'selected' : ''; ?>>All Statuses</option>
                            <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="paused" <?php echo $statusFilter === 'paused' ? 'selected' : ''; ?>>Paused</option>
                            <option value="cancelled" <?php echo $statusFilter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            <option value="expired" <?php echo $statusFilter === 'expired' ? 'selected' : ''; ?>>Expired</option>
                        </select>
                    </div>

                    <!-- Plan Filter -->
                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-600 mb-1">Plan</label>
                        <select name="plan_id" class="w-full px-3 py-1.5 rounded-lg border border-slate-300 text-xs outline-none focus:ring-2 focus:ring-amber-500 bg-white">
                            <option value="0">All Plans</option>
                            <?php foreach ($plans as $p): ?>
                                <option value="<?php echo $p['id']; ?>" <?php echo $planFilter === (int)$p['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($p['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Occasion Filter -->
                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-600 mb-1">Occasion</label>
                        <select name="occasion_type" class="w-full px-3 py-1.5 rounded-lg border border-slate-300 text-xs outline-none focus:ring-2 focus:ring-amber-500 bg-white">
                            <option value="all">All Occasions</option>
                            <?php foreach ($distinctOccasions as $occ): ?>
                                <option value="<?php echo htmlspecialchars($occ); ?>" <?php echo $occasionFilter === $occ ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($occ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Sort By -->
                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-600 mb-1">Sort By</label>
                        <select name="sort" class="w-full px-3 py-1.5 rounded-lg border border-slate-300 text-xs outline-none focus:ring-2 focus:ring-amber-500 bg-white">
                            <option value="newest" <?php echo $sortField === 'newest' ? 'selected' : ''; ?>>Newest Created</option>
                            <option value="oldest" <?php echo $sortField === 'oldest' ? 'selected' : ''; ?>>Oldest Created</option>
                            <option value="next_delivery" <?php echo $sortField === 'next_delivery' ? 'selected' : ''; ?>>Next Delivery</option>
                            <option value="occasion_date" <?php echo $sortField === 'occasion_date' ? 'selected' : ''; ?>>Occasion Date</option>
                            <option value="customer_name" <?php echo $sortField === 'customer_name' ? 'selected' : ''; ?>>Customer Name</option>
                            <option value="plan" <?php echo $sortField === 'plan' ? 'selected' : ''; ?>>Plan Name</option>
                            <option value="status" <?php echo $sortField === 'status' ? 'selected' : ''; ?>>Status</option>
                        </select>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-1 border-t border-slate-200/60">
                    <a href="admin.php?tab=subscriptions&sub=subscribers" class="px-3 py-1 text-xs text-slate-500 hover:text-slate-800">
                        Reset Filters
                    </a>
                    <button type="submit" class="px-4 py-1.5 rounded-lg bg-slate-900 text-white text-xs font-bold hover:bg-slate-800 transition-colors">
                        Apply Filters
                    </button>
                </div>
            </form>

            <!-- Subscribers Table -->
            <?php if (empty($subscribers)): ?>
                <div class="text-center py-14 space-y-2">
                    <div class="text-4xl">👥</div>
                    <p class="text-sm font-semibold text-slate-700">No subscribers found.</p>
                    <p class="text-xs text-slate-400">
                        <?php echo !empty($searchFilter) || !empty($statusFilter) || $planFilter > 0 ? 'No subscriptions match your current filter selections.' : 'No customer subscriptions have been placed yet.'; ?>
                    </p>
                </div>
            <?php else: ?>
                <div class="table-wrapper">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-100/70 text-slate-600 uppercase text-[11px] font-bold tracking-wider border-b border-slate-200">
                            <tr>
                                <th class="py-3 px-3"># ID</th>
                                <th class="py-3 px-4">Customer</th>
                                <th class="py-3 px-3">Subscription Plan</th>
                                <th class="py-3 px-3">Occasion</th>
                                <th class="py-3 px-3">Recipient & City</th>
                                <th class="py-3 px-3">Next Delivery</th>
                                <th class="py-3 px-3">Status</th>
                                <th class="py-3 px-3">Created</th>
                                <th class="py-3 px-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($subscribers as $idx => $s): ?>
                                <tr class="hover:bg-slate-50/80 transition-colors">
                                    <!-- Subscription ID -->
                                    <td class="py-3.5 px-3">
                                        <span class="font-mono text-xs font-bold text-slate-700 bg-slate-100 px-1.5 py-0.5 rounded border border-slate-200">
                                            #<?php echo substr($s['id'], 0, 8); ?>
                                        </span>
                                    </td>

                                    <!-- Customer Details -->
                                    <td class="py-3.5 px-4 min-w-[170px]">
                                        <strong class="text-slate-900 font-bold block"><?php echo htmlspecialchars($s['customer_name']); ?></strong>
                                        <span class="text-xs text-slate-500 block truncate"><?php echo htmlspecialchars($s['customer_email'] ?: 'No email'); ?></span>
                                        <?php if (!empty($s['customer_phone'])): ?>
                                            <span class="text-[11px] text-slate-400 block"><?php echo htmlspecialchars($s['customer_phone']); ?></span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Plan & Frequency -->
                                    <td class="py-3.5 px-3">
                                        <div class="flex items-center gap-2">
                                            <?php if (!empty($s['plan_image'])): ?>
                                                <img src="<?php echo htmlspecialchars($s['plan_image']); ?>" class="w-8 h-8 rounded-lg object-cover border border-slate-200 shrink-0" alt="Plan">
                                            <?php endif; ?>
                                            <div>
                                                <span class="font-bold text-slate-800 block text-xs truncate max-w-[140px]">
                                                    <?php echo htmlspecialchars($s['plan_name'] ?? 'Custom Plan'); ?>
                                                </span>
                                                <span class="text-[10px] text-slate-500 capitalize">
                                                    <?php echo htmlspecialchars($s['plan_frequency'] ?? 'monthly'); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Occasion Type & Date -->
                                    <td class="py-3.5 px-3 text-xs">
                                        <strong class="text-slate-800 block"><?php echo htmlspecialchars($s['occasion_type']); ?></strong>
                                        <span class="text-slate-400 text-[11px] block font-mono"><?php echo htmlspecialchars($s['occasion_date']); ?></span>
                                    </td>

                                    <!-- Recipient & City -->
                                    <td class="py-3.5 px-3 text-xs">
                                        <span class="text-slate-800 font-medium block"><?php echo htmlspecialchars($s['recipient_name'] ?: 'Same as Customer'); ?></span>
                                        <span class="text-slate-400 text-[11px] block"><?php echo htmlspecialchars($s['city'] ?: 'Bangalore'); ?></span>
                                    </td>

                                    <!-- Next Delivery Date -->
                                    <td class="py-3.5 px-3 text-xs">
                                        <?php if (!empty($s['next_delivery_date'])): ?>
                                            <span class="font-mono font-bold text-amber-800 bg-amber-100/70 border border-amber-300 px-2 py-0.5 rounded text-[11px]">
                                                <?php echo htmlspecialchars($s['next_delivery_date']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-slate-400 italic text-[11px]">Not scheduled</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Status Badge -->
                                    <td class="py-3.5 px-3 text-xs">
                                        <?php
                                        $badgeClasses = [
                                            'active'    => 'bg-emerald-100 text-emerald-800 border-emerald-200',
                                            'paused'    => 'bg-amber-100 text-amber-800 border-amber-200',
                                            'cancelled' => 'bg-rose-100 text-rose-800 border-rose-200',
                                            'expired'   => 'bg-slate-100 text-slate-700 border-slate-200',
                                        ];
                                        $statusClass = $badgeClasses[$s['status']] ?? 'bg-slate-100 text-slate-700 border-slate-200';
                                        ?>
                                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider border <?php echo $statusClass; ?>">
                                            ● <?php echo htmlspecialchars($s['status']); ?>
                                        </span>
                                    </td>

                                    <!-- Created Date -->
                                    <td class="py-3.5 px-3 text-[11px] text-slate-400 font-mono">
                                        <?php echo date('d M Y', strtotime($s['created_at'])); ?>
                                    </td>

                                    <!-- Actions Menu -->
                                    <td class="py-3.5 px-4 text-right">
                                        <div class="flex items-center justify-end gap-1.5 flex-wrap">
                                            <!-- View Details Modal Trigger -->
                                            <button onclick='openViewSubscriberModal(<?php echo htmlspecialchars(json_encode($s), ENT_QUOTES); ?>)'
                                                    class="px-2.5 py-1 text-xs font-semibold rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 transition-colors">
                                                View
                                            </button>

                                            <!-- Edit Subscriber Trigger -->
                                            <button onclick='openEditSubscriberModal(<?php echo htmlspecialchars(json_encode($s), ENT_QUOTES); ?>)'
                                                    class="px-2.5 py-1 text-xs font-semibold rounded-lg bg-slate-800 hover:bg-slate-900 text-white transition-colors">
                                                Edit
                                            </button>

                                            <!-- Status Quick-Actions dropdown or direct buttons -->
                                            <?php if ($s['status'] === 'active'): ?>
                                                <form method="POST" onsubmit="return confirm('Pause this subscription? Deliveries will be temporarily suspended.');" class="inline">
                                                    <input type="hidden" name="action" value="update_subscriber_status">
                                                    <input type="hidden" name="subscription_id" value="<?php echo $s['id']; ?>">
                                                    <input type="hidden" name="status" value="paused">
                                                    <button type="submit" class="px-2 py-1 text-xs rounded-lg bg-amber-50 text-amber-700 hover:bg-amber-100 font-medium">
                                                        Pause
                                                    </button>
                                                </form>
                                            <?php elseif ($s['status'] === 'paused'): ?>
                                                <form method="POST" onsubmit="return confirm('Resume this subscription? Deliveries will be restored.');" class="inline">
                                                    <input type="hidden" name="action" value="update_subscriber_status">
                                                    <input type="hidden" name="subscription_id" value="<?php echo $s['id']; ?>">
                                                    <input type="hidden" name="status" value="active">
                                                    <button type="submit" class="px-2 py-1 text-xs rounded-lg bg-emerald-50 text-emerald-700 hover:bg-emerald-100 font-medium">
                                                        Resume
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                            <?php if ($s['status'] !== 'cancelled'): ?>
                                                <form method="POST" onsubmit="return confirm('Cancel this subscription? Historical records are preserved.');" class="inline">
                                                    <input type="hidden" name="action" value="update_subscriber_status">
                                                    <input type="hidden" name="subscription_id" value="<?php echo $s['id']; ?>">
                                                    <input type="hidden" name="status" value="cancelled">
                                                    <button type="submit" class="px-2 py-1 text-xs rounded-lg text-rose-600 hover:bg-rose-50 font-medium">
                                                        Cancel
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Component -->
                <div class="flex flex-col sm:flex-row items-center justify-between gap-4 pt-4 border-t border-slate-100 text-xs text-slate-500">
                    <div>
                        Showing <strong><?php echo min($totalSubscriberRecords, ($currentPage - 1) * $perPage + 1); ?>-<?php echo min($totalSubscriberRecords, $currentPage * $perPage); ?></strong>
                        of <strong><?php echo $totalSubscriberRecords; ?></strong> subscribers
                    </div>

                    <?php if ($totalPages > 1): ?>
                        <div class="flex items-center gap-1">
                            <?php if ($currentPage > 1): ?>
                                <a href="admin.php?tab=subscriptions&sub=subscribers&page=<?php echo $currentPage - 1; ?>&search=<?php echo urlencode($searchFilter); ?>&status=<?php echo urlencode($statusFilter); ?>&plan_id=<?php echo $planFilter; ?>&sort=<?php echo urlencode($sortField); ?>"
                                   class="px-3 py-1.5 rounded-lg border border-slate-200 hover:bg-slate-50 font-semibold text-slate-700">
                                    Previous
                                </a>
                            <?php endif; ?>

                            <?php for ($pNum = max(1, $currentPage - 2); $pNum <= min($totalPages, $currentPage + 2); $pNum++): ?>
                                <a href="admin.php?tab=subscriptions&sub=subscribers&page=<?php echo $pNum; ?>&search=<?php echo urlencode($searchFilter); ?>&status=<?php echo urlencode($statusFilter); ?>&plan_id=<?php echo $planFilter; ?>&sort=<?php echo urlencode($sortField); ?>"
                                   class="px-3 py-1.5 rounded-lg border font-bold <?php echo $pNum === $currentPage ? 'bg-amber-500 border-amber-500 text-slate-950' : 'border-slate-200 text-slate-700 hover:bg-slate-50'; ?>">
                                    <?php echo $pNum; ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($currentPage < $totalPages): ?>
                                <a href="admin.php?tab=subscriptions&sub=subscribers&page=<?php echo $currentPage + 1; ?>&search=<?php echo urlencode($searchFilter); ?>&status=<?php echo urlencode($statusFilter); ?>&plan_id=<?php echo $planFilter; ?>&sort=<?php echo urlencode($sortField); ?>"
                                   class="px-3 py-1.5 rounded-lg border border-slate-200 hover:bg-slate-50 font-semibold text-slate-700">
                                    Next
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>


<!-- ═══════════════════════════════════════════════════════════════════════════ -->
<!-- MODALS                                                                     -->
<!-- ═══════════════════════════════════════════════════════════════════════════ -->

<!-- 1. MODAL: ADD / EDIT SUBSCRIPTION PLAN -->
<div id="planFormModal" class="fixed inset-0 z-50 bg-slate-950/70 backdrop-blur-xs hidden items-center justify-center p-4">
    <div class="bg-white max-w-2xl w-full rounded-3xl p-6 shadow-2xl space-y-4 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <h3 id="planModalTitle" class="text-lg font-serif font-bold text-slate-900">Add Subscription Plan</h3>
            <button type="button" onclick="closePlanFormModal()" class="text-slate-400 hover:text-slate-600 text-lg font-bold">✕</button>
        </div>

        <form method="POST" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" id="plan_action_type" name="action" value="create_plan">
            <input type="hidden" id="modal_plan_id" name="plan_id" value="">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Plan Name *</label>
                    <input type="text" id="modal_plan_name" name="name" required placeholder="e.g. Monthly Bloom"
                           class="w-full px-4 py-2 rounded-xl border border-slate-300 text-sm outline-none focus:ring-2 focus:ring-amber-500">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Slug (URL friendly)</label>
                    <input type="text" id="modal_plan_slug" name="slug" placeholder="e.g. monthly-bloom"
                           class="w-full px-4 py-2 rounded-xl border border-slate-300 text-sm outline-none focus:ring-2 focus:ring-amber-500">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Tagline</label>
                <input type="text" id="modal_plan_tagline" name="tagline" placeholder="e.g. Fresh curated joy, every month"
                       class="w-full px-4 py-2 rounded-xl border border-slate-300 text-sm outline-none focus:ring-2 focus:ring-amber-500">
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Description</label>
                <textarea id="modal_plan_description" name="description" rows="3" placeholder="Full plan description for customers..."
                          class="w-full px-4 py-2 rounded-xl border border-slate-300 text-sm outline-none focus:ring-2 focus:ring-amber-500"></textarea>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Frequency *</label>
                    <select id="modal_plan_frequency" name="frequency" class="w-full px-3 py-2 rounded-xl border border-slate-300 text-sm outline-none focus:ring-2 focus:ring-amber-500 bg-white">
                        <option value="monthly">Monthly</option>
                        <option value="quarterly">Quarterly</option>
                        <option value="yearly">Yearly</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Deliv / Year</label>
                    <input type="number" id="modal_plan_deliveries" name="deliveries_per_year" value="12" min="1"
                           class="w-full px-3 py-2 rounded-xl border border-slate-300 text-sm outline-none focus:ring-2 focus:ring-amber-500">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Price / Deliv (₹) *</label>
                    <input type="number" step="0.01" id="modal_plan_price" name="price_per_delivery" required placeholder="999"
                           class="w-full px-3 py-2 rounded-xl border border-slate-300 text-sm outline-none focus:ring-2 focus:ring-amber-500">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Total (₹)</label>
                    <input type="number" step="0.01" id="modal_plan_total" name="total_price" placeholder="11988"
                           class="w-full px-3 py-2 rounded-xl border border-slate-300 text-sm outline-none focus:ring-2 focus:ring-amber-500">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Savings %</label>
                    <input type="number" id="modal_plan_savings" name="savings_percent" value="0" min="0" max="100"
                           class="w-full px-4 py-2 rounded-xl border border-slate-300 text-sm outline-none focus:ring-2 focus:ring-amber-500">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Display Order</label>
                    <input type="number" id="modal_plan_order" name="display_order" value="1" min="1"
                           class="w-full px-4 py-2 rounded-xl border border-slate-300 text-sm outline-none focus:ring-2 focus:ring-amber-500">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Features (1 per line)</label>
                <textarea id="modal_plan_features" name="features" rows="4" placeholder="1 curated delivery per month&#10;Handpicked seasonal blooms&#10;Free priority delivery"
                          class="w-full px-4 py-2 rounded-xl border border-slate-300 text-sm outline-none focus:ring-2 focus:ring-amber-500"></textarea>
            </div>

            <!-- Image Upload Section (only on create; on edit, dedicated image modal can also be used) -->
            <div id="plan_create_image_section">
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Plan Cover Image (Product Pipeline)</label>
                <input type="file" name="plan_image" accept="image/jpeg,image/png,image/webp"
                       class="w-full px-3 py-2 rounded-xl border border-slate-300 text-xs bg-slate-50">
                <p class="text-[11px] text-slate-400 mt-1">Processed to 1000×1000 square GD crop and stored in permanent store.</p>
            </div>

            <div class="flex items-center gap-6 pt-2">
                <label class="flex items-center gap-2 cursor-pointer text-xs font-bold text-slate-700">
                    <input type="checkbox" id="modal_plan_popular" name="is_popular" value="1" class="w-4 h-4 text-amber-600 rounded">
                    ★ Mark as Most Popular
                </label>
                <label class="flex items-center gap-2 cursor-pointer text-xs font-bold text-slate-700">
                    <input type="checkbox" id="modal_plan_active" name="is_active" value="1" checked class="w-4 h-4 text-emerald-600 rounded">
                    ● Active on Public Site
                </label>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
                <button type="button" onclick="closePlanFormModal()" class="px-4 py-2 text-slate-600 text-sm font-semibold">Cancel</button>
                <button type="submit" class="px-5 py-2 gold-gradient text-slate-950 font-bold rounded-xl text-sm shadow-md">
                    Save Plan
                </button>
            </div>
        </form>
    </div>
</div>

<!-- 2. MODAL: DEDICATED IMAGE UPLOAD & PREVIEW (Product Pipeline) -->
<div id="planImageModal" class="fixed inset-0 z-50 bg-slate-950/70 backdrop-blur-xs hidden items-center justify-center p-4">
    <div class="bg-white max-w-lg w-full rounded-3xl p-6 shadow-2xl space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div>
                <h3 class="text-lg font-serif font-bold text-slate-900">Manage Plan Image</h3>
                <p id="imageModalPlanName" class="text-xs text-slate-500 font-semibold"></p>
            </div>
            <button type="button" onclick="closePlanImageModal()" class="text-slate-400 hover:text-slate-600 text-lg font-bold">✕</button>
        </div>

        <form method="POST" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="action" value="update_plan_image">
            <input type="hidden" id="img_modal_plan_id" name="plan_id" value="">
            <input type="hidden" id="remove_image_flag" name="remove_image" value="0">

            <!-- Image Preview Box -->
            <div class="flex flex-col items-center justify-center p-4 bg-slate-50 rounded-2xl border border-slate-200">
                <div id="imagePreviewWrapper" class="relative w-44 h-44 rounded-2xl overflow-hidden border-2 border-amber-400 shadow-md mb-2">
                    <img id="imagePreview" src="" alt="Plan Preview" class="w-full h-full object-cover">
                    <div class="absolute inset-0 bg-black/40 opacity-0 hover:opacity-100 flex items-center justify-center transition-opacity">
                        <button type="button" onclick="removeImage()" class="px-3 py-1.5 bg-rose-600 text-white text-xs font-bold rounded-xl shadow-md">
                            Remove Image
                        </button>
                    </div>
                </div>
                <div id="noImagePlaceholder" class="hidden w-44 h-44 rounded-2xl border-2 border-dashed border-slate-300 flex items-center justify-center text-xs text-slate-400 italic mb-2">
                    No image saved
                </div>
                <p id="previewLabel" class="text-xs text-slate-500 font-medium"></p>
            </div>

            <!-- Upload Input -->
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Choose New Image</label>
                <input type="file" id="plan_image_input" name="plan_image" accept="image/jpeg,image/png,image/webp"
                       onchange="previewPlanImage(this)"
                       class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-xs bg-white file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-amber-100 file:text-amber-900">
                <p class="text-[11px] text-slate-400 mt-1">Accepts JPG, PNG, WEBP (Max 5MB). Auto-cropped to 1000×1000 px square JPEG.</p>
            </div>

            <div class="flex justify-end gap-3 pt-3 border-t border-slate-100">
                <button type="button" onclick="closePlanImageModal()" class="px-4 py-2 text-slate-600 text-sm font-semibold">Cancel</button>
                <button type="submit" class="px-5 py-2 gold-gradient text-slate-950 font-bold rounded-xl text-sm shadow-md">Save Image</button>
=======
// ── HANDLE POST: Save/Update Subscription Plan Image ────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!$is_main_admin) {
        $error = 'Unauthorized. Only main_admin can modify subscription plans.';
    } else {
        $action = $_POST['action'];

        if ($action === 'update_plan') {
            $planId = (int)($_POST['plan_id'] ?? 0);

            if (!$planId) {
                $error = 'Plan ID is required.';
            } else {
                try {
                    $curr = $db->prepare("SELECT * FROM subscription_plans WHERE id = :id LIMIT 1");
                    $curr->execute([':id' => $planId]);
                    $plan = $curr->fetch(PDO::FETCH_ASSOC);

                    if (!$plan) {
                        throw new Exception("Subscription plan not found.");
                    }

                    $existingImage = $plan['image'] ?? '';

                    // Handle Remove Image flag
                    if (isset($_POST['remove_image']) && $_POST['remove_image'] === '1') {
                        if (!empty($existingImage) && strpos($existingImage, OMG_IMG_URL_PATH) !== false) {
                            deleteLocalImage($existingImage);
                        }
                        $newImage = '';
                    } else {
                        // Process file upload through Product pipeline
                        $newImage = handleSubscriptionImageUpload('plan_image', $plan['name'], $existingImage);
                    }

                    $stmt = $db->prepare("UPDATE subscription_plans SET image = :image WHERE id = :id");
                    $stmt->execute([':image' => $newImage, ':id' => $planId]);

                    $message = "Subscription plan image updated successfully!";

                } catch (Exception $e) {
                    $error = $e->getMessage();
                }
            }
        }
    }
}

// Fetch all plans for display
try {
    $plans = $db->query("SELECT * FROM subscription_plans ORDER BY display_order ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $plans = [];
    $error = 'Could not load subscription plans: ' . $e->getMessage();
}

$pageTitle = "Subscription Plans Management";
require_once 'admin_header.php';
?>

<div class="space-y-8">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-200 pb-6">
        <div>
            <h1 class="text-2xl sm:text-3xl font-serif font-bold text-slate-900">Subscription Plans</h1>
            <p class="text-slate-500 text-sm mt-1">Manage subscription plan images. All uploads are processed to 1000×1000 px using the same pipeline as products.</p>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl text-sm font-medium">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 rounded-xl text-sm font-medium">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <!-- Subscription Plans Table -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 space-y-6">
        <div class="border-b border-slate-100 pb-4">
            <h3 class="text-lg font-serif font-bold text-slate-900">Subscription Plans — Image Management</h3>
            <p class="text-xs text-slate-500 mt-1">Click "Edit Image" to upload or replace a plan's cover image. Files are validated (MIME + extension + size), cropped to 1000×1000 px, and stored in the permanent image store.</p>
        </div>

        <div class="table-wrapper">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-100/70 text-slate-600 uppercase text-[11px] font-bold tracking-wider border-b border-slate-200">
                    <tr>
                        <th class="py-3 px-4">Order</th>
                        <th class="py-3 px-4">Image</th>
                        <th class="py-3 px-4">Subscription Plan</th>
                        <th class="py-3 px-4">Frequency</th>
                        <th class="py-3 px-4">Price / Delivery</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($plans as $plan): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="py-3.5 px-4 text-xs font-mono font-bold text-slate-500">#<?php echo $plan['display_order']; ?></td>
                            <td class="py-3.5 px-4">
                                <?php if (!empty($plan['image'])): ?>
                                    <img src="<?php echo htmlspecialchars($plan['image']); ?>"
                                        alt="<?php echo htmlspecialchars($plan['name']); ?>"
                                        class="w-14 h-14 object-cover rounded-lg border border-slate-200 shadow-sm">
                                <?php else: ?>
                                    <div class="w-14 h-14 rounded-lg border border-dashed border-slate-300 bg-slate-50 flex items-center justify-center text-[10px] text-slate-400 text-center leading-tight">
                                        No<br>Image
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="py-3.5 px-4">
                                <p class="font-bold text-slate-900"><?php echo htmlspecialchars($plan['name']); ?></p>
                                <p class="text-xs text-slate-500 italic"><?php echo htmlspecialchars($plan['tagline'] ?? ''); ?></p>
                            </td>
                            <td class="py-3.5 px-4 text-xs font-semibold text-slate-700 capitalize"><?php echo htmlspecialchars($plan['frequency']); ?></td>
                            <td class="py-3.5 px-4 font-bold text-amber-600">&#8377;<?php echo number_format($plan['price_per_delivery']); ?></td>
                            <td class="py-3.5 px-4 text-xs">
                                <span class="<?php echo $plan['is_active'] ? 'text-emerald-600 font-semibold' : 'text-slate-400'; ?>">
                                    <?php echo $plan['is_active'] ? '&#9679; Active' : '&#9675; Inactive'; ?>
                                </span>
                            </td>
                            <td class="py-3.5 px-4 text-right">
                                <button onclick="openPlanImageModal(<?php echo htmlspecialchars(json_encode([
                                    'id'    => $plan['id'],
                                    'name'  => $plan['name'],
                                    'image' => $plan['image'] ?? '',
                                ]), ENT_QUOTES); ?>)"
                                    class="px-3 py-1.5 bg-slate-800 text-white rounded-lg text-xs font-medium hover:bg-slate-900 transition-colors">
                                    Edit Image
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- MODAL: Edit Subscription Plan Image -->
<div id="planImageModal"
    class="hidden fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white max-w-md w-full rounded-2xl p-6 shadow-2xl space-y-5">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <h3 id="planModalTitle" class="text-lg font-serif font-bold text-slate-900">Edit Subscription Image</h3>
            <button type="button" onclick="closePlanImageModal()"
                class="text-slate-400 hover:text-slate-600 font-bold text-2xl leading-none">&times;</button>
        </div>

        <form id="planImageForm" method="POST" enctype="multipart/form-data" class="space-y-5">
            <input type="hidden" name="action" value="update_plan">
            <input type="hidden" id="plan_id_input" name="plan_id" value="">
            <input type="hidden" id="remove_image_flag" name="remove_image" value="0">

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-2">
                    Plan Cover Image
                </label>

                <!-- Current / Preview Image -->
                <div id="imagePreviewWrapper" class="mb-3 hidden">
                    <div class="relative w-40 h-40 rounded-xl overflow-hidden border border-slate-200 shadow-sm bg-slate-50 mx-auto">
                        <img id="imagePreview" src="" alt="Plan Image Preview" class="w-full h-full object-cover">
                        <button type="button" onclick="removeImage()"
                            class="absolute top-1.5 right-1.5 bg-rose-500 hover:bg-rose-600 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs font-bold shadow-md transition-colors"
                            title="Remove Image">&times;</button>
                    </div>
                    <p class="text-[11px] text-slate-400 text-center mt-1" id="previewLabel">Current saved image</p>
                </div>

                <!-- No image placeholder -->
                <div id="noImagePlaceholder"
                    class="mb-3 w-40 h-40 rounded-xl border-2 border-dashed border-slate-300 bg-slate-50 flex flex-col items-center justify-center mx-auto">
                    <svg class="w-10 h-10 text-slate-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <p class="text-xs text-slate-400">No image set</p>
                </div>

                <!-- File input -->
                <label for="plan_image_input"
                    class="flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl border-2 border-dashed border-slate-300 hover:border-amber-400 hover:bg-amber-50 cursor-pointer transition-all text-sm font-semibold text-slate-600 hover:text-amber-700">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                    </svg>
                    Choose Image
                </label>
                <input type="file" id="plan_image_input" name="plan_image"
                    accept="image/jpeg,image/jpg,image/png,image/webp"
                    class="hidden" onchange="previewPlanImage(this)">
                <p class="text-[11px] text-slate-400 text-center mt-2">JPG, PNG, WEBP &middot; Max 5 MB &middot; Auto-cropped to 1000&times;1000 px</p>
            </div>

            <div class="flex justify-end gap-3 pt-3 border-t border-slate-100">
                <button type="button" onclick="closePlanImageModal()"
                    class="px-4 py-2 text-slate-600 text-sm font-semibold hover:text-slate-800 transition-colors">Cancel</button>
                <?php if ($is_main_admin): ?>
                    <button type="submit"
                        class="px-5 py-2 gold-gradient text-slate-950 font-bold rounded-xl text-sm shadow-md hover:opacity-90">
                        Save Image
                    </button>
                <?php else: ?>
                    <span class="px-4 py-2 text-xs text-slate-400 italic">Only main_admin can save images.</span>
                <?php endif; ?>
>>>>>>> b34855a241af95ea619ff4cb20e5c1044d14eec8
            </div>
        </form>
    </div>
</div>

<<<<<<< HEAD
<!-- 3. MODAL: VIEW PLAN DETAILS -->
<div id="viewPlanModal" class="fixed inset-0 z-50 bg-slate-950/70 backdrop-blur-xs hidden items-center justify-center p-4">
    <div class="bg-white max-w-lg w-full rounded-3xl p-6 shadow-2xl space-y-4 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <h3 class="text-lg font-serif font-bold text-slate-900" id="vp_title">Plan Details</h3>
            <button type="button" onclick="closeViewPlanModal()" class="text-slate-400 hover:text-slate-600 text-lg font-bold">✕</button>
        </div>

        <div class="space-y-4 text-xs text-slate-700">
            <div class="w-full h-48 rounded-2xl overflow-hidden border border-slate-200">
                <img id="vp_image" src="" alt="Plan Cover" class="w-full h-full object-cover">
            </div>

            <div class="grid grid-cols-2 gap-3 bg-slate-50 p-4 rounded-xl border border-slate-200/60">
                <div>
                    <span class="text-slate-400 text-[10px] uppercase font-bold block">Frequency</span>
                    <strong id="vp_frequency" class="text-slate-800 capitalize text-sm"></strong>
                </div>
                <div>
                    <span class="text-slate-400 text-[10px] uppercase font-bold block">Deliveries / Year</span>
                    <strong id="vp_deliveries" class="text-slate-800 text-sm"></strong>
                </div>
                <div>
                    <span class="text-slate-400 text-[10px] uppercase font-bold block">Price Per Delivery</span>
                    <strong id="vp_price" class="text-amber-600 font-bold text-sm"></strong>
                </div>
                <div>
                    <span class="text-slate-400 text-[10px] uppercase font-bold block">Total Price</span>
                    <strong id="vp_total" class="text-slate-900 font-bold text-sm"></strong>
                </div>
            </div>

            <div>
                <span class="text-slate-400 text-[10px] uppercase font-bold block mb-1">Description</span>
                <p id="vp_description" class="text-slate-600 leading-relaxed"></p>
            </div>

            <div>
                <span class="text-slate-400 text-[10px] uppercase font-bold block mb-1">Included Features</span>
                <ul id="vp_features" class="list-disc pl-5 space-y-1 text-slate-600"></ul>
            </div>
        </div>

        <div class="flex justify-end pt-3 border-t border-slate-100">
            <button type="button" onclick="closeViewPlanModal()" class="px-5 py-2 bg-slate-900 text-white font-bold rounded-xl text-xs">Close</button>
        </div>
    </div>
</div>

<!-- 4. MODAL: VIEW SUBSCRIBER FULL DETAILS -->
<div id="viewSubscriberModal" class="fixed inset-0 z-50 bg-slate-950/70 backdrop-blur-xs hidden items-center justify-center p-4">
    <div class="bg-white max-w-xl w-full rounded-3xl p-6 shadow-2xl space-y-4 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div>
                <h3 class="text-lg font-serif font-bold text-slate-900">Subscriber Details</h3>
                <span id="vs_sub_id" class="font-mono text-xs text-slate-400"></span>
            </div>
            <button type="button" onclick="closeViewSubscriberModal()" class="text-slate-400 hover:text-slate-600 text-lg font-bold">✕</button>
        </div>

        <div class="space-y-4 text-xs text-slate-700">
            <!-- Customer Section -->
            <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200/70 space-y-2">
                <h4 class="text-[11px] font-bold uppercase tracking-wider text-amber-800">👤 Customer</h4>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <span class="text-slate-400 text-[10px] block">Name</span>
                        <strong id="vs_cust_name" class="text-slate-800 text-sm"></strong>
                    </div>
                    <div>
                        <span class="text-slate-400 text-[10px] block">Email</span>
                        <span id="vs_cust_email" class="text-slate-700"></span>
                    </div>
                    <div>
                        <span class="text-slate-400 text-[10px] block">Phone</span>
                        <span id="vs_cust_phone" class="text-slate-700"></span>
                    </div>
                    <div>
                        <span class="text-slate-400 text-[10px] block">User ID</span>
                        <span id="vs_user_id" class="font-mono text-[10px] text-slate-500"></span>
                    </div>
                </div>
            </div>

            <!-- Subscription Section -->
            <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200/70 space-y-2">
                <h4 class="text-[11px] font-bold uppercase tracking-wider text-amber-800">🌸 Subscription Plan</h4>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <span class="text-slate-400 text-[10px] block">Plan</span>
                        <strong id="vs_plan_name" class="text-slate-800 text-sm"></strong>
                    </div>
                    <div>
                        <span class="text-slate-400 text-[10px] block">Status</span>
                        <span id="vs_status" class="font-bold uppercase text-[11px]"></span>
                    </div>
                    <div>
                        <span class="text-slate-400 text-[10px] block">Frequency</span>
                        <span id="vs_frequency" class="capitalize text-slate-700"></span>
                    </div>
                    <div>
                        <span class="text-slate-400 text-[10px] block">Occasion</span>
                        <span id="vs_occasion" class="text-slate-700"></span>
                    </div>
                </div>
            </div>

            <!-- Delivery & Recipient Section -->
            <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200/70 space-y-2">
                <h4 class="text-[11px] font-bold uppercase tracking-wider text-amber-800">🚚 Delivery Information</h4>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <span class="text-slate-400 text-[10px] block">Recipient</span>
                        <strong id="vs_recip_name" class="text-slate-800"></strong>
                    </div>
                    <div>
                        <span class="text-slate-400 text-[10px] block">Recipient Phone</span>
                        <span id="vs_recip_phone" class="text-slate-700"></span>
                    </div>
                    <div class="col-span-2">
                        <span class="text-slate-400 text-[10px] block">Address</span>
                        <p id="vs_address" class="text-slate-800 mt-0.5 leading-relaxed"></p>
                    </div>
                    <div>
                        <span class="text-slate-400 text-[10px] block">City</span>
                        <span id="vs_city" class="text-slate-700"></span>
                    </div>
                    <div>
                        <span class="text-slate-400 text-[10px] block">Next Delivery Date</span>
                        <strong id="vs_next_delivery" class="text-amber-700 font-mono"></strong>
                    </div>
                </div>
            </div>

            <!-- Delivery Summary & Notes -->
            <div class="grid grid-cols-2 gap-3">
                <div class="p-3 rounded-xl border border-slate-200 bg-white">
                    <span class="text-slate-400 text-[10px] uppercase font-bold block">Total Deliveries Made</span>
                    <strong id="vs_total_deliv" class="text-slate-800 text-sm font-bold"></strong>
                </div>
                <div class="p-3 rounded-xl border border-slate-200 bg-white">
                    <span class="text-slate-400 text-[10px] uppercase font-bold block">Joined Date</span>
                    <span id="vs_created_at" class="text-slate-700 font-mono text-[11px]"></span>
                </div>
            </div>

            <div id="vs_notes_wrapper">
                <span class="text-slate-400 text-[10px] uppercase font-bold block mb-1">Customer / Admin Notes</span>
                <p id="vs_notes" class="p-3 rounded-xl bg-slate-50 border border-slate-200 text-slate-600 italic"></p>
            </div>
        </div>

        <div class="flex justify-end pt-3 border-t border-slate-100">
            <button type="button" onclick="closeViewSubscriberModal()" class="px-5 py-2 bg-slate-900 text-white font-bold rounded-xl text-xs">Close</button>
        </div>
    </div>
</div>

<!-- 5. MODAL: EDIT SUBSCRIBER DETAILS -->
<div id="editSubscriberModal" class="fixed inset-0 z-50 bg-slate-950/70 backdrop-blur-xs hidden items-center justify-center p-4">
    <div class="bg-white max-w-lg w-full rounded-3xl p-6 shadow-2xl space-y-4 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <h3 class="text-lg font-serif font-bold text-slate-900">Edit Subscription Details</h3>
            <button type="button" onclick="closeEditSubscriberModal()" class="text-slate-400 hover:text-slate-600 text-lg font-bold">✕</button>
        </div>

        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="edit_subscriber">
            <input type="hidden" id="es_sub_id" name="subscription_id" value="">

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Subscription Plan *</label>
                <select id="es_plan_id" name="plan_id" required class="w-full px-3 py-2 rounded-xl border border-slate-300 text-sm outline-none focus:ring-2 focus:ring-amber-500 bg-white">
                    <?php foreach ($plans as $p): ?>
                        <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['name']); ?> (₹<?php echo number_format($p['price_per_delivery']); ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Occasion Type *</label>
                    <input type="text" id="es_occasion_type" name="occasion_type" required
                           class="w-full px-3 py-2 rounded-xl border border-slate-300 text-sm outline-none focus:ring-2 focus:ring-amber-500">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Occasion Date *</label>
                    <input type="date" id="es_occasion_date" name="occasion_date" required
                           class="w-full px-3 py-2 rounded-xl border border-slate-300 text-sm outline-none focus:ring-2 focus:ring-amber-500">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Recipient Name</label>
                    <input type="text" id="es_recipient_name" name="recipient_name"
                           class="w-full px-3 py-2 rounded-xl border border-slate-300 text-sm outline-none focus:ring-2 focus:ring-amber-500">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Recipient Phone</label>
                    <input type="text" id="es_recipient_phone" name="recipient_phone"
                           class="w-full px-3 py-2 rounded-xl border border-slate-300 text-sm outline-none focus:ring-2 focus:ring-amber-500">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Delivery Address</label>
                <textarea id="es_delivery_address" name="delivery_address" rows="2"
                          class="w-full px-3 py-2 rounded-xl border border-slate-300 text-sm outline-none focus:ring-2 focus:ring-amber-500"></textarea>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">City</label>
                    <input type="text" id="es_city" name="city" value="Bangalore"
                           class="w-full px-3 py-2 rounded-xl border border-slate-300 text-sm outline-none focus:ring-2 focus:ring-amber-500">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Status</label>
                    <select id="es_status" name="status" class="w-full px-3 py-2 rounded-xl border border-slate-300 text-sm outline-none focus:ring-2 focus:ring-amber-500 bg-white">
                        <option value="active">Active</option>
                        <option value="paused">Paused</option>
                        <option value="cancelled">Cancelled</option>
                        <option value="expired">Expired</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Next Delivery Date</label>
                    <input type="date" id="es_next_delivery" name="next_delivery_date"
                           class="w-full px-3 py-2 rounded-xl border border-slate-300 text-sm outline-none focus:ring-2 focus:ring-amber-500">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Total Deliveries</label>
                    <input type="number" id="es_total_deliveries" name="total_deliveries" min="0"
                           class="w-full px-3 py-2 rounded-xl border border-slate-300 text-sm outline-none focus:ring-2 focus:ring-amber-500">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Notes</label>
                <textarea id="es_notes" name="notes" rows="2" placeholder="Special preferences, instructions, etc."
                          class="w-full px-3 py-2 rounded-xl border border-slate-300 text-sm outline-none focus:ring-2 focus:ring-amber-500"></textarea>
            </div>

            <div class="flex justify-end gap-3 pt-3 border-t border-slate-100">
                <button type="button" onclick="closeEditSubscriberModal()" class="px-4 py-2 text-slate-600 text-sm font-semibold">Cancel</button>
                <button type="submit" class="px-5 py-2 gold-gradient text-slate-950 font-bold rounded-xl text-sm shadow-md">Update Subscription</button>
            </div>
        </form>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════ -->
<!-- SCRIPTS FOR INTERACTIVITY                                                 -->
<!-- ═══════════════════════════════════════════════════════════════════════════ -->
<script>
    // ── PLAN FORM MODAL (Add / Edit) ─────────────────────────────────────────
    function openAddPlanModal() {
        document.getElementById('planModalTitle').textContent = 'Add Subscription Plan';
        document.getElementById('plan_action_type').value = 'create_plan';
        document.getElementById('modal_plan_id').value = '';
        document.getElementById('modal_plan_name').value = '';
        document.getElementById('modal_plan_slug').value = '';
        document.getElementById('modal_plan_tagline').value = '';
        document.getElementById('modal_plan_description').value = '';
        document.getElementById('modal_plan_frequency').value = 'monthly';
        document.getElementById('modal_plan_deliveries').value = '12';
        document.getElementById('modal_plan_price').value = '';
        document.getElementById('modal_plan_total').value = '';
        document.getElementById('modal_plan_savings').value = '0';
        document.getElementById('modal_plan_order').value = '1';
        document.getElementById('modal_plan_features').value = '';
        document.getElementById('modal_plan_popular').checked = false;
        document.getElementById('modal_plan_active').checked = true;
        document.getElementById('plan_create_image_section').classList.remove('hidden');

        document.getElementById('planFormModal').classList.remove('hidden');
        document.getElementById('planFormModal').classList.add('flex');
    }

    function openEditPlanModal(plan) {
        document.getElementById('planModalTitle').textContent = 'Edit Subscription Plan: ' + plan.name;
        document.getElementById('plan_action_type').value = 'edit_plan';
        document.getElementById('modal_plan_id').value = plan.id;
        document.getElementById('modal_plan_name').value = plan.name || '';
        document.getElementById('modal_plan_slug').value = plan.slug || '';
        document.getElementById('modal_plan_tagline').value = plan.tagline || '';
        document.getElementById('modal_plan_description').value = plan.description || '';
        document.getElementById('modal_plan_frequency').value = plan.frequency || 'monthly';
        document.getElementById('modal_plan_deliveries').value = plan.deliveries_per_year || 12;
        document.getElementById('modal_plan_price').value = plan.price_per_delivery || '';
        document.getElementById('modal_plan_total').value = plan.total_price || '';
        document.getElementById('modal_plan_savings').value = plan.savings_percent || 0;
        document.getElementById('modal_plan_order').value = plan.display_order || 1;

        let feats = [];
        try {
            feats = JSON.parse(plan.features);
            if (!Array.isArray(feats) && feats.features) feats = feats.features;
        } catch (e) {
            feats = [plan.features];
        }
        document.getElementById('modal_plan_features').value = Array.isArray(feats) ? feats.join('\n') : '';

        document.getElementById('modal_plan_popular').checked = plan.is_popular == 1;
        document.getElementById('modal_plan_active').checked = plan.is_active == 1;
        document.getElementById('plan_create_image_section').classList.add('hidden');

        document.getElementById('planFormModal').classList.remove('hidden');
        document.getElementById('planFormModal').classList.add('flex');
    }

    function closePlanFormModal() {
        document.getElementById('planFormModal').classList.add('hidden');
        document.getElementById('planFormModal').classList.remove('flex');
    }

    // Auto-calculate Total Price in Plan Form
    document.getElementById('modal_plan_price').addEventListener('input', autoCalcPlanTotal);
    document.getElementById('modal_plan_deliveries').addEventListener('input', autoCalcPlanTotal);
    function autoCalcPlanTotal() {
        const price = parseFloat(document.getElementById('modal_plan_price').value) || 0;
        const count = parseInt(document.getElementById('modal_plan_deliveries').value) || 0;
        if (price > 0 && count > 0) {
            document.getElementById('modal_plan_total').value = (price * count).toFixed(2);
        }
    }

    // ── DEDICATED PLAN IMAGE MODAL ───────────────────────────────────────────
    function openPlanImageModal(plan) {
        document.getElementById('img_modal_plan_id').value = plan.id;
        document.getElementById('imageModalPlanName').textContent = plan.name;
        document.getElementById('remove_image_flag').value = '0';
        document.getElementById('plan_image_input').value = '';

        if (plan.image && plan.image.trim() !== '') {
=======
<script>
    function openPlanImageModal(plan) {
        document.getElementById('planModalTitle').innerText = 'Edit Image: ' + plan.name;
        document.getElementById('plan_id_input').value = plan.id;
        document.getElementById('remove_image_flag').value = '0';
        document.getElementById('plan_image_input').value = '';

        if (plan.image) {
>>>>>>> b34855a241af95ea619ff4cb20e5c1044d14eec8
            document.getElementById('imagePreview').src = plan.image;
            document.getElementById('imagePreviewWrapper').classList.remove('hidden');
            document.getElementById('noImagePlaceholder').classList.add('hidden');
            document.getElementById('previewLabel').textContent = 'Current saved image';
        } else {
            document.getElementById('imagePreviewWrapper').classList.add('hidden');
            document.getElementById('noImagePlaceholder').classList.remove('hidden');
<<<<<<< HEAD
            document.getElementById('previewLabel').textContent = 'No image set';
        }

        document.getElementById('planImageModal').classList.remove('hidden');
        document.getElementById('planImageModal').classList.add('flex');
=======
        }

        document.getElementById('planImageModal').classList.remove('hidden');
>>>>>>> b34855a241af95ea619ff4cb20e5c1044d14eec8
    }

    function closePlanImageModal() {
        document.getElementById('planImageModal').classList.add('hidden');
<<<<<<< HEAD
        document.getElementById('planImageModal').classList.remove('flex');
=======
        document.getElementById('plan_image_input').value = '';
        document.getElementById('remove_image_flag').value = '0';
>>>>>>> b34855a241af95ea619ff4cb20e5c1044d14eec8
    }

    function previewPlanImage(input) {
        if (!input.files || !input.files[0]) return;
        const file = input.files[0];

<<<<<<< HEAD
        const allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
        if (!allowed.includes(file.type)) {
            alert('Invalid file format. Please upload JPG, PNG, or WEBP.');
=======
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
        if (!allowedTypes.includes(file.type)) {
            alert('Invalid file type. Only JPG, PNG, and WEBP are allowed.');
>>>>>>> b34855a241af95ea619ff4cb20e5c1044d14eec8
            input.value = '';
            return;
        }
        if (file.size > 5 * 1024 * 1024) {
<<<<<<< HEAD
            alert('File exceeds 5 MB size limit.');
=======
            alert('File is too large. Maximum size is 5 MB.');
>>>>>>> b34855a241af95ea619ff4cb20e5c1044d14eec8
            input.value = '';
            return;
        }

        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('imagePreview').src = e.target.result;
            document.getElementById('imagePreviewWrapper').classList.remove('hidden');
            document.getElementById('noImagePlaceholder').classList.add('hidden');
<<<<<<< HEAD
            document.getElementById('previewLabel').textContent = 'Selected: ' + file.name + ' (will be cropped to 1000×1000)';
=======
            document.getElementById('previewLabel').textContent = 'New image (not yet saved)';
>>>>>>> b34855a241af95ea619ff4cb20e5c1044d14eec8
            document.getElementById('remove_image_flag').value = '0';
        };
        reader.readAsDataURL(file);
    }

    function removeImage() {
<<<<<<< HEAD
        if (!confirm('Remove this image? It will be deleted from storage when you save.')) return;
=======
        if (!confirm('Remove this image? It will be deleted when you save.')) return;
>>>>>>> b34855a241af95ea619ff4cb20e5c1044d14eec8
        document.getElementById('imagePreview').src = '';
        document.getElementById('imagePreviewWrapper').classList.add('hidden');
        document.getElementById('noImagePlaceholder').classList.remove('hidden');
        document.getElementById('plan_image_input').value = '';
        document.getElementById('remove_image_flag').value = '1';
<<<<<<< HEAD
        document.getElementById('previewLabel').textContent = 'Image marked for removal';
    }

    // ── VIEW PLAN MODAL ──────────────────────────────────────────────────────
    function openViewPlanModal(plan) {
        document.getElementById('vp_title').textContent = plan.name;
        document.getElementById('vp_image').src = plan.image || 'https://images.unsplash.com/photo-1561181286-d3fee7d55364?auto=format&fit=crop&w=600&q=80';
        document.getElementById('vp_frequency').textContent = plan.frequency;
        document.getElementById('vp_deliveries').textContent = plan.deliveries_per_year + ' times/year';
        document.getElementById('vp_price').textContent = '₹' + Number(plan.price_per_delivery).toLocaleString('en-IN');
        document.getElementById('vp_total').textContent = '₹' + Number(plan.total_price).toLocaleString('en-IN');
        document.getElementById('vp_description').textContent = plan.description || 'No description provided.';

        const fList = document.getElementById('vp_features');
        fList.innerHTML = '';
        let feats = [];
        try {
            feats = JSON.parse(plan.features);
            if (!Array.isArray(feats) && feats.features) feats = feats.features;
        } catch (e) {
            feats = [plan.features];
        }
        if (Array.isArray(feats)) {
            feats.forEach(f => {
                if (f && f.trim()) {
                    const li = document.createElement('li');
                    li.textContent = f;
                    fList.appendChild(li);
                }
            });
        }

        document.getElementById('viewPlanModal').classList.remove('hidden');
        document.getElementById('viewPlanModal').classList.add('flex');
    }

    function closeViewPlanModal() {
        document.getElementById('viewPlanModal').classList.add('hidden');
        document.getElementById('viewPlanModal').classList.remove('flex');
    }

    // ── VIEW SUBSCRIBER MODAL ────────────────────────────────────────────────
    function openViewSubscriberModal(s) {
        document.getElementById('vs_sub_id').textContent = 'ID: ' + s.id;
        document.getElementById('vs_cust_name').textContent = s.customer_name || 'Customer';
        document.getElementById('vs_cust_email').textContent = s.customer_email || '—';
        document.getElementById('vs_cust_phone').textContent = s.customer_phone || '—';
        document.getElementById('vs_user_id').textContent = s.user_id || '—';

        document.getElementById('vs_plan_name').textContent = s.plan_name || 'Custom Plan';
        document.getElementById('vs_status').textContent = s.status || 'active';
        document.getElementById('vs_status').className = 'font-bold uppercase text-[11px] ' + 
            (s.status === 'active' ? 'text-emerald-700' : (s.status === 'paused' ? 'text-amber-700' : 'text-rose-700'));
        document.getElementById('vs_frequency').textContent = s.plan_frequency || 'monthly';
        document.getElementById('vs_occasion').textContent = s.occasion_type + ' (' + s.occasion_date + ')';

        document.getElementById('vs_recip_name').textContent = s.recipient_name || s.customer_name;
        document.getElementById('vs_recip_phone').textContent = s.recipient_phone || s.customer_phone || '—';
        document.getElementById('vs_address').textContent = s.delivery_address || 'No street address specified';
        document.getElementById('vs_city').textContent = s.city || 'Bangalore';
        document.getElementById('vs_next_delivery').textContent = s.next_delivery_date || 'Not Scheduled';

        document.getElementById('vs_total_deliv').textContent = (s.total_deliveries || 0) + ' deliveries';
        document.getElementById('vs_created_at').textContent = s.created_at || '—';

        if (s.notes && s.notes.trim()) {
            document.getElementById('vs_notes_wrapper').classList.remove('hidden');
            document.getElementById('vs_notes').textContent = s.notes;
        } else {
            document.getElementById('vs_notes_wrapper').classList.add('hidden');
        }

        document.getElementById('viewSubscriberModal').classList.remove('hidden');
        document.getElementById('viewSubscriberModal').classList.add('flex');
    }

    function closeViewSubscriberModal() {
        document.getElementById('viewSubscriberModal').classList.add('hidden');
        document.getElementById('viewSubscriberModal').classList.remove('flex');
    }

    // ── EDIT SUBSCRIBER MODAL ────────────────────────────────────────────────
    function openEditSubscriberModal(s) {
        document.getElementById('es_sub_id').value = s.id;
        document.getElementById('es_plan_id').value = s.plan_id;
        document.getElementById('es_occasion_type').value = s.occasion_type || '';
        document.getElementById('es_occasion_date').value = s.occasion_date || '';
        document.getElementById('es_recipient_name').value = s.recipient_name || '';
        document.getElementById('es_recipient_phone').value = s.recipient_phone || '';
        document.getElementById('es_delivery_address').value = s.delivery_address || '';
        document.getElementById('es_city').value = s.city || 'Bangalore';
        document.getElementById('es_status').value = s.status || 'active';
        document.getElementById('es_next_delivery').value = s.next_delivery_date || '';
        document.getElementById('es_total_deliveries').value = s.total_deliveries || 0;
        document.getElementById('es_notes').value = s.notes || '';

        document.getElementById('editSubscriberModal').classList.remove('hidden');
        document.getElementById('editSubscriberModal').classList.add('flex');
    }

    function closeEditSubscriberModal() {
        document.getElementById('editSubscriberModal').classList.add('hidden');
        document.getElementById('editSubscriberModal').classList.remove('flex');
    }

    // Modal click-outside dismissal
    ['planFormModal', 'planImageModal', 'viewPlanModal', 'viewSubscriberModal', 'editSubscriberModal'].forEach(function(modalId) {
        const el = document.getElementById(modalId);
        if (el) {
            el.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.add('hidden');
                    this.classList.remove('flex');
                }
            });
        }
=======
    }

    document.getElementById('planImageModal').addEventListener('click', function(e) {
        if (e.target === this) closePlanImageModal();
>>>>>>> b34855a241af95ea619ff4cb20e5c1044d14eec8
    });
</script>

<?php require_once 'admin_footer.php'; ?>
