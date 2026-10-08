<?php
/**
 * admin_subscriptions.php — OMG Floral Store Subscription Management System
 *
 * Fully integrated Admin Panel for Subscriptions:
 *   1. Subscription Plans Section
 *      - Header with "+ Add Subscription Plan" button
 *      - Responsive Table: Order (# with Up/Down), Image (1000x1000 GD crop),
 *        Subscription Plan (Name, Tagline, Slug, Popular badge), Frequency,
 *        Price / Delivery, Subscribers count column (COUNT(subscriptions.id)),
 *        Status toggle (Active/Disabled), and Actions (Edit Image, Edit Plan, View, Duplicate, Safe Delete)
 *   2. Subscription Overview Section
 *      - Real metrics: Total Subscribers, Active, Paused, Cancelled, Expired, Most Subscribed Plan
 *      - Upcoming Deliveries & Recent Subscriptions quick cards
 *   3. Subscribers Section (Immediately Below Plans)
 *      - Search by Customer name, email, phone, recipient, subscription ID
 *      - Multi-field filters: Status, Plan, Frequency, Occasion, City, Date ranges (Created & Next Delivery)
 *      - Sorting by Customer, Plan, Price/Value, Next Delivery, Status, Created Date
 *      - Responsive table with 13 columns
 *      - Real-time status actions: Pause, Resume, Cancel, Expire
 *      - Server-side pagination and CSV export
 *   4. Modals
 *      - Add / Edit Subscription Plan (All 14 fields with Product image upload pipeline)
 *      - Dedicated Image Cropper (1000×1000 square GD crop with dual storage)
 *      - View Plan Details
 *      - View Subscriber Details (Customer, Subscription, Occasion, Recipient, Delivery, Notes)
 *      - Edit Subscriber
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

function handleSubscriptionImageUpload(string $fileKey, string $planName = '', string $existingUrl = ''): string
{
    if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
        return $existingUrl;
    }

    $file     = $_FILES[$fileKey];
    $size     = $file['size'];
    $tmpName  = $file['tmp_name'];
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

    $primaryDir   = OMG_PRIMARY_DIR;
    $secondaryDir = OMG_SECONDARY_DIR;

    if (!is_dir($primaryDir) && !mkdir($primaryDir, 0755, true)) {
        throw new Exception('Permanent image directory could not be created.');
    }
    if (!is_writable($primaryDir)) {
        throw new Exception('Permanent image directory is not writable.');
    }
    if (!is_dir($secondaryDir)) {
        @mkdir($secondaryDir, 0755, true);
    }

    $targetFilename = generateProductImageFilename(
        !empty($planName) ? $planName : 'subscription',
        'main',
        'jpg'
    );

    $primaryTarget   = $primaryDir   . $targetFilename;
    $secondaryTarget = $secondaryDir . $targetFilename;

    if (!cropToSquare1000($tmpName, $primaryTarget)) {
        throw new Exception('Failed to crop and save the uploaded image.');
    }

    if (is_writable($secondaryDir) && !copy($primaryTarget, $secondaryTarget)) {
        error_log('[OMG Upload] copy() to backend/uploads/ failed: ' . $targetFilename);
    }

    if (!empty($existingUrl) && strpos($existingUrl, OMG_IMG_URL_PATH) !== false) {
        $oldFilename = basename($existingUrl);
        if ($oldFilename !== $targetFilename) {
            deleteLocalImage($existingUrl);
        }
    }

    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    return $protocol . '://' . $_SERVER['HTTP_HOST'] . OMG_IMG_URL_PATH . $targetFilename;
}

$message = '';
$error   = '';

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
        'Price Per Delivery (INR)',
        'Total Plan Price (INR)',
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
        'Created Date'
    ]);

    $csvStmt = $db->query("SELECT 
        s.id,
        COALESCE(up.name, s.recipient_name, 'Customer') AS customer_name,
        COALESCE(u.email, '') AS customer_email,
        COALESCE(up.phone, s.recipient_phone, '') AS customer_phone,
        COALESCE(sp.name, 'Custom Plan') AS plan_name,
        COALESCE(sp.frequency, 'monthly') AS plan_frequency,
        COALESCE(sp.price_per_delivery, 0) AS plan_price_per_delivery,
        COALESCE(sp.total_price, 0) AS plan_total_price,
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
        s.created_at
    FROM subscriptions s
    LEFT JOIN users u ON s.user_id = u.id
    LEFT JOIN user_profiles up ON s.user_id = up.id
    LEFT JOIN subscription_plans sp ON s.plan_id = sp.id
    ORDER BY s.created_at DESC");

    while ($row = $csvStmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($out, [
            $row['id'],
            $row['customer_name'],
            $row['customer_email'],
            $row['customer_phone'],
            $row['plan_name'],
            ucfirst($row['plan_frequency']),
            $row['plan_price_per_delivery'],
            $row['plan_total_price'],
            $row['occasion_type'],
            $row['occasion_date'],
            $row['recipient_name'],
            $row['recipient_phone'],
            $row['delivery_address'],
            $row['city'],
            ucfirst($row['status']),
            $row['next_delivery_date'] ?: 'Not scheduled',
            $row['total_deliveries'],
            $row['notes'],
            $row['created_at']
        ]);
    }
    fclose($out);
    exit();
}

// ── PROCESS POST REQUESTS (Plans CRUD, Image Upload, Status Changes) ─────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        // 1. CREATE SUBSCRIPTION PLAN
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
            if (empty($slug)) {
                $slug = slugify($name);
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

            // Fetch current plan image
            $currImgStmt = $db->prepare("SELECT image FROM subscription_plans WHERE id = ?");
            $currImgStmt->execute([$id]);
            $currentImg = (string)$currImgStmt->fetchColumn();

            $newImage = $currentImg;
            if (isset($_POST['remove_image']) && $_POST['remove_image'] === '1') {
                if (!empty($currentImg) && strpos($currentImg, OMG_IMG_URL_PATH) !== false) {
                    deleteLocalImage($currentImg);
                }
                $newImage = null;
            } elseif (isset($_FILES['plan_image']) && $_FILES['plan_image']['error'] === UPLOAD_ERR_OK) {
                $newImage = handleSubscriptionImageUpload('plan_image', $name, $currentImg);
            }

            $stmt = $db->prepare("UPDATE subscription_plans SET 
                name = ?, slug = ?, tagline = ?, description = ?, frequency = ?, 
                deliveries_per_year = ?, price_per_delivery = ?, total_price = ?, 
                savings_percent = ?, features = ?, image = ?, is_popular = ?, is_active = ?, display_order = ?
                WHERE id = ?");
            $stmt->execute([
                $name, $slug, $tagline, $description, $frequency,
                $deliveries_per_yr, $price_per_delivery, $total_price,
                $savings_percent, $features_json, $newImage, $is_popular, $is_active,
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
                $newImage = null;
            } elseif (isset($_FILES['plan_image']) && $_FILES['plan_image']['error'] === UPLOAD_ERR_OK) {
                $newImage = handleSubscriptionImageUpload('plan_image', $plan['name'], $existingImage);
            } elseif (!empty($_POST['image_url'])) {
                $newImage = trim($_POST['image_url']);
            } else {
                $newImage = $existingImage;
            }

            $up = $db->prepare("UPDATE subscription_plans SET image = ? WHERE id = ?");
            $up->execute([$newImage, $planId]);

            $message = "Cover image for plan '{$plan['name']}' updated successfully!";
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

            if (!in_array($newStatus, $allowedStatuses, true)) {
                throw new Exception("Invalid status '{$newStatus}'. Allowed: " . implode(', ', $allowedStatuses));
            }

            $upSub = $db->prepare("UPDATE subscriptions SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $upSub->execute([$newStatus, $subId]);

            $message = "Subscription status updated to " . strtoupper($newStatus) . " successfully.";
        }

        // 10. EDIT SUBSCRIBER RECORD (Recipient, Delivery, Occasion, Notes, Status)
        elseif ($action === 'edit_subscriber') {
            $subId          = trim($_POST['subscription_id'] ?? '');
            $planId         = (int)($_POST['plan_id'] ?? 0);
            $recipientName  = trim($_POST['recipient_name'] ?? '');
            $recipientPhone = trim($_POST['recipient_phone'] ?? '');
            $occasionType   = trim($_POST['occasion_type'] ?? '');
            $occasionDate   = trim($_POST['occasion_date'] ?? '');
            $deliveryAddr   = trim($_POST['delivery_address'] ?? '');
            $city           = trim($_POST['city'] ?? 'Bangalore');
            $status         = trim($_POST['status'] ?? 'active');
            $nextDelivery   = trim($_POST['next_delivery_date'] ?? '');
            $totalDeliv     = (int)($_POST['total_deliveries'] ?? 0);
            $notes          = trim($_POST['notes'] ?? '');

            if (empty($subId)) {
                throw new Exception("Subscription ID is required.");
            }

            $upStmt = $db->prepare("UPDATE subscriptions SET 
                plan_id = ?, recipient_name = ?, recipient_phone = ?, occasion_type = ?,
                occasion_date = ?, delivery_address = ?, city = ?, status = ?,
                next_delivery_date = ?, total_deliveries = ?, notes = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?");
            $upStmt->execute([
                $planId, $recipientName, $recipientPhone, $occasionType, $occasionDate, $deliveryAddr,
                $city, $status, !empty($nextDelivery) ? $nextDelivery : null,
                $totalDeliv, $notes, $subId
            ]);

            $message = "Subscriber details updated successfully!";
        }

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
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
    'most_popular_plan' => 'None',
    'upcoming_deliveries_count' => 0
];

try {
    $subStatsStmt = $db->query("SELECT 
        COUNT(*) as total_cnt,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_cnt,
        SUM(CASE WHEN status = 'paused' THEN 1 ELSE 0 END) as paused_cnt,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_cnt,
        SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired_cnt
        FROM subscriptions");
    $rawSt = $subStatsStmt->fetch(PDO::FETCH_ASSOC);
    if ($rawSt) {
        $stats['total_subscribers'] = (int)($rawSt['total_cnt'] ?? 0);
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

    // Upcoming deliveries count (status = active AND next_delivery_date >= CURRENT_DATE)
    $upCntStmt = $db->query("SELECT COUNT(*) FROM subscriptions WHERE status = 'active' AND next_delivery_date >= CURRENT_DATE");
    $stats['upcoming_deliveries_count'] = (int)$upCntStmt->fetchColumn();
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
          AND s.next_delivery_date >= CURRENT_DATE
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
$perPage = max(5, min(100, (int)($_GET['per_page'] ?? 15)));
$currentPage = max(1, (int)($_GET['page'] ?? 1));

// Filter parameters
$searchFilter       = trim($_GET['search'] ?? '');
$statusFilter       = trim($_GET['status'] ?? '');
$planFilter         = (int)($_GET['plan_id'] ?? 0);
$frequencyFilter    = trim($_GET['frequency'] ?? '');
$occasionFilter     = trim($_GET['occasion_type'] ?? '');
$cityFilter         = trim($_GET['city'] ?? '');
$createdFrom        = trim($_GET['created_from'] ?? '');
$createdTo          = trim($_GET['created_to'] ?? '');
$nextDeliveryFrom   = trim($_GET['next_delivery_from'] ?? '');
$nextDeliveryTo     = trim($_GET['next_delivery_to'] ?? '');
$sortField          = trim($_GET['sort'] ?? 'newest');

try {
    $where = ["1=1"];
    $params = [];

    // Search in Customer Name, Email, Phone, Recipient Name, Subscription ID, Address
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

    // Date Filters
    if ($createdFrom !== '') {
        $where[] = "s.created_at >= :cfrom";
        $params[':cfrom'] = $createdFrom . ' 00:00:00';
    }
    if ($createdTo !== '') {
        $where[] = "s.created_at <= :cto";
        $params[':cto'] = $createdTo . ' 23:59:59';
    }
    if ($nextDeliveryFrom !== '') {
        $where[] = "s.next_delivery_date >= :ndfrom";
        $params[':ndfrom'] = $nextDeliveryFrom;
    }
    if ($nextDeliveryTo !== '') {
        $where[] = "s.next_delivery_date <= :ndto";
        $params[':ndto'] = $nextDeliveryTo;
    }

    $whereClause = implode(" AND ", $where);

    // Sorting
    $orderClause = "s.created_at DESC";
    switch ($sortField) {
        case 'oldest':
            $orderClause = "s.created_at ASC";
            break;
        case 'next_delivery':
            $orderClause = "s.next_delivery_date IS NULL, s.next_delivery_date ASC, s.created_at DESC";
            break;
        case 'occasion_date':
            $orderClause = "s.occasion_date ASC";
            break;
        case 'customer':
            $orderClause = "COALESCE(up.name, s.recipient_name) ASC";
            break;
        case 'plan':
            $orderClause = "sp.name ASC";
            break;
        case 'price_value':
            $orderClause = "sp.total_price DESC";
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
            sp.savings_percent AS plan_savings_percent,
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

// Helper: Distinct Occasion Types for Filter
$distinctOccasions = [];
try {
    $distinctOccasions = $db->query("SELECT DISTINCT occasion_type FROM subscriptions WHERE occasion_type IS NOT NULL AND occasion_type != '' ORDER BY occasion_type ASC")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

$pageTitle = "Subscription Plans & Management — OMG Floral Store";
require_once 'admin_header.php';
?>

<div class="space-y-8 pb-12">
    <!-- Top Header Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200 pb-5">
        <div>
            <h1 class="text-2xl sm:text-3xl font-serif font-bold text-slate-900">Subscription Plans</h1>
            <p class="text-slate-500 text-xs sm:text-sm mt-0.5">Manage subscription plan images. All uploads are processed to 1000×1000 px using the same pipeline as products.</p>
        </div>

        <div class="flex items-center gap-2.5 flex-wrap">
            <a href="#subscribersSection" 
               class="inline-flex items-center gap-1.5 px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-xs font-semibold shadow-xs transition-colors">
                👥 View Subscribers (<?php echo $stats['total_subscribers']; ?>)
            </a>
            <button type="button" onclick="openAddPlanModal()" 
                    class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl gold-gradient text-slate-950 text-xs font-bold shadow-md hover:brightness-105 transition-all">
                ✨ + Add Subscription Plan
            </button>
        </div>
    </div>

    <!-- Feedback Alerts -->
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

    <!-- ═══════════════════════════════════════════════════════════════════════════ -->
    <!-- SECTION 1: SUBSCRIPTION PLANS (Image Management & CRUD)                    -->
    <!-- ═══════════════════════════════════════════════════════════════════════════ -->
    <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs p-6 space-y-5">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-100 pb-4">
            <div>
                <h2 class="text-lg font-serif font-bold text-slate-900">Subscription Plans — Image Management</h2>
                <p class="text-xs text-slate-500 mt-0.5">Click "Edit Image" to upload or replace a plan's cover image. Files are validated (MIME + extension + size), cropped to 1000×1000 px, and stored in the permanent image store.</p>
            </div>
            <button type="button" onclick="openAddPlanModal()" 
                    class="px-4 py-2 rounded-xl gold-gradient text-slate-950 text-xs font-bold shadow-md hover:brightness-105 shrink-0 self-start sm:self-auto">
                + Add Subscription Plan
            </button>
        </div>

        <?php if (empty($plans)): ?>
            <div class="text-center py-12 text-slate-400 italic text-sm">
                No subscription plans found. Click "+ Add Subscription Plan" to create one.
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm border-collapse">
                    <thead class="bg-slate-50 text-slate-500 uppercase text-[11px] font-bold tracking-wider border-b border-slate-200">
                        <tr>
                            <th class="py-3 px-3 w-16">ORDER</th>
                            <th class="py-3 px-3 w-20">IMAGE</th>
                            <th class="py-3 px-4">SUBSCRIPTION PLAN</th>
                            <th class="py-3 px-3">FREQUENCY</th>
                            <th class="py-3 px-3">PRICE / DELIVERY</th>
                            <th class="py-3 px-3">SUBSCRIBERS</th>
                            <th class="py-3 px-3">STATUS</th>
                            <th class="py-3 px-4 text-right">ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($plans as $p): ?>
                            <tr class="hover:bg-slate-50/70 transition-colors">
                                <!-- Order (# and Up/Down) -->
                                <td class="py-4 px-3 text-xs text-slate-600">
                                    <div class="flex items-center gap-1.5">
                                        <span class="font-mono font-bold text-slate-700 bg-slate-100 px-2 py-0.5 rounded text-xs">
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
                                <td class="py-4 px-3">
                                    <div class="relative group w-14 h-14 rounded-xl overflow-hidden border border-slate-200 shadow-xs cursor-pointer bg-slate-100"
                                         onclick='openPlanImageModal(<?php echo htmlspecialchars(json_encode($p), ENT_QUOTES); ?>)'>
                                        <?php if (!empty($p['image'])): ?>
                                            <img src="<?php echo htmlspecialchars($p['image']); ?>" alt="<?php echo htmlspecialchars($p['name']); ?>" 
                                                 class="w-full h-full object-cover">
                                        <?php else: ?>
                                            <div class="w-full h-full flex items-center justify-center text-[10px] text-slate-400 text-center leading-tight">
                                                No<br>Image
                                            </div>
                                        <?php endif; ?>
                                        <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 flex items-center justify-center transition-opacity text-[10px] text-white font-bold">
                                            Edit
                                        </div>
                                    </div>
                                </td>

                                <!-- Subscription Plan Name & Tagline -->
                                <td class="py-4 px-4 min-w-[200px]">
                                    <div class="flex items-center gap-2">
                                        <strong class="text-slate-900 font-bold text-sm"><?php echo htmlspecialchars($p['name']); ?></strong>
                                        <?php if ($p['is_popular']): ?>
                                            <span class="text-[9px] font-bold px-1.5 py-0.5 rounded bg-amber-100 text-amber-800 border border-amber-300">Popular</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($p['tagline'])): ?>
                                        <p class="text-xs text-slate-500 italic mt-0.5"><?php echo htmlspecialchars($p['tagline']); ?></p>
                                    <?php endif; ?>
                                </td>

                                <!-- Frequency -->
                                <td class="py-4 px-3 text-xs font-semibold capitalize text-slate-700">
                                    <?php echo htmlspecialchars($p['frequency']); ?>
                                </td>

                                <!-- Price / Delivery -->
                                <td class="py-4 px-3 font-bold text-amber-600 text-sm">
                                    ₹<?php echo number_format($p['price_per_delivery']); ?>
                                </td>

                                <!-- Subscribers Count Column (Calculated from database) -->
                                <td class="py-4 px-3 text-xs">
                                    <a href="admin.php?tab=subscriptions&plan_id=<?php echo $p['id']; ?>#subscribersSection" 
                                       class="inline-flex items-center gap-1.5 font-bold text-slate-800 hover:text-amber-700 bg-slate-100 hover:bg-amber-100/60 px-2.5 py-1 rounded-lg transition-colors border border-slate-200"
                                       title="Filter subscribers below by this plan">
                                        <span>👥</span>
                                        <span><?php echo (int)($p['subscriber_count'] ?? 0); ?> Subscribers</span>
                                    </a>
                                </td>

                                <!-- Status Toggle -->
                                <td class="py-4 px-3 text-xs">
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="action" value="toggle_plan_active">
                                        <input type="hidden" name="plan_id" value="<?php echo $p['id']; ?>">
                                        <button type="submit" class="cursor-pointer font-semibold text-xs <?php echo $p['is_active'] ? 'text-emerald-600 hover:text-emerald-800' : 'text-slate-400 hover:text-slate-600'; ?>">
                                            <?php echo $p['is_active'] ? '● Active' : '○ Disabled'; ?>
                                        </button>
                                    </form>
                                </td>

                                <!-- Actions Menu -->
                                <td class="py-4 px-4 text-right">
                                    <div class="flex items-center justify-end gap-1.5 flex-wrap">
                                        <!-- Edit Image Button (Primary button from screenshot) -->
                                        <button type="button" 
                                                onclick='openPlanImageModal(<?php echo htmlspecialchars(json_encode($p), ENT_QUOTES); ?>)'
                                                class="px-3.5 py-1.5 rounded-lg bg-slate-900 hover:bg-slate-800 text-white text-xs font-semibold transition-all shadow-xs">
                                            Edit Image
                                        </button>

                                        <!-- Edit Plan Form Button -->
                                        <button type="button" 
                                                onclick='openEditPlanModal(<?php echo htmlspecialchars(json_encode($p), ENT_QUOTES); ?>)'
                                                class="px-2.5 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold transition-colors"
                                                title="Edit all plan details">
                                            Edit Plan
                                        </button>

                                        <!-- View Plan Details Modal -->
                                        <button type="button" 
                                                onclick='openViewPlanModal(<?php echo htmlspecialchars(json_encode($p), ENT_QUOTES); ?>)'
                                                class="px-2.5 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold transition-colors">
                                            View
                                        </button>

                                        <!-- Duplicate Plan -->
                                        <form method="POST" onsubmit="return confirm('Duplicate plan as a new draft?');" class="inline">
                                            <input type="hidden" name="action" value="duplicate_plan">
                                            <input type="hidden" name="plan_id" value="<?php echo $p['id']; ?>">
                                            <button type="submit" class="p-1.5 rounded-lg text-slate-500 hover:text-slate-800 hover:bg-slate-100" title="Duplicate Plan">
                                                📋
                                            </button>
                                        </form>

                                        <!-- Safe Delete (protected if subscribers exist) -->
                                        <form method="POST" onsubmit="return confirm('Are you sure you want to delete this plan? If subscribers exist, deletion will be safely blocked.');" class="inline">
                                            <input type="hidden" name="action" value="delete_plan">
                                            <input type="hidden" name="plan_id" value="<?php echo $p['id']; ?>">
                                            <button type="submit" class="p-1.5 rounded-lg text-rose-500 hover:text-rose-700 hover:bg-rose-50" title="Delete Plan">
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


    <!-- ═══════════════════════════════════════════════════════════════════════════ -->
    <!-- SECTION 2: SUBSCRIPTION OVERVIEW (Real Database Metrics)                    -->
    <!-- ═══════════════════════════════════════════════════════════════════════════ -->
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="text-base font-serif font-bold text-slate-900 flex items-center gap-2">
                <span>📊</span> Subscription Overview
            </h2>
            <span class="text-xs text-slate-400">Real-time counts across MySQL records</span>
        </div>

        <!-- 6 Compact Stat Cards -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
            <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs">
                <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Total Subscribers</p>
                <p class="text-2xl font-serif font-bold text-slate-900 mt-1"><?php echo $stats['total_subscribers']; ?></p>
                <p class="text-[10px] text-slate-400 mt-0.5">All customer records</p>
            </div>
            <div class="bg-white p-4 rounded-2xl border border-emerald-200 bg-emerald-50/20 shadow-xs">
                <p class="text-[10px] font-bold text-emerald-700 uppercase tracking-wider">Active</p>
                <p class="text-2xl font-serif font-bold text-emerald-600 mt-1"><?php echo $stats['active']; ?></p>
                <p class="text-[10px] text-emerald-600/70 mt-0.5">Receiving deliveries</p>
            </div>
            <div class="bg-white p-4 rounded-2xl border border-amber-200 bg-amber-50/20 shadow-xs">
                <p class="text-[10px] font-bold text-amber-700 uppercase tracking-wider">Paused</p>
                <p class="text-2xl font-serif font-bold text-amber-600 mt-1"><?php echo $stats['paused']; ?></p>
                <p class="text-[10px] text-amber-600/70 mt-0.5">Temporarily on hold</p>
            </div>
            <div class="bg-white p-4 rounded-2xl border border-rose-200 bg-rose-50/20 shadow-xs">
                <p class="text-[10px] font-bold text-rose-700 uppercase tracking-wider">Cancelled</p>
                <p class="text-2xl font-serif font-bold text-rose-600 mt-1"><?php echo $stats['cancelled']; ?></p>
                <p class="text-[10px] text-rose-600/70 mt-0.5">Retained for history</p>
            </div>
            <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-xs">
                <p class="text-[10px] font-bold text-slate-600 uppercase tracking-wider">Expired</p>
                <p class="text-2xl font-serif font-bold text-slate-500 mt-1"><?php echo $stats['expired']; ?></p>
                <p class="text-[10px] text-slate-400 mt-0.5">Term concluded</p>
            </div>
            <div class="bg-white p-4 rounded-2xl border border-amber-300 bg-gradient-to-br from-white to-amber-50/30 shadow-xs">
                <p class="text-[10px] font-bold text-amber-800 uppercase tracking-wider truncate">Top Plan</p>
                <p class="text-sm font-bold text-slate-900 mt-2 truncate" title="<?php echo htmlspecialchars($stats['most_popular_plan']); ?>">
                    <?php echo htmlspecialchars($stats['most_popular_plan']); ?>
                </p>
                <p class="text-[10px] text-amber-700 mt-0.5">Most subscribed</p>
            </div>
        </div>

        <!-- Two Quick Preview Cards: Upcoming Deliveries & Recent Subscriptions -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <!-- Upcoming Deliveries -->
            <div class="bg-white rounded-2xl border border-slate-200/80 p-5 space-y-3 shadow-xs">
                <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
                    <div class="flex items-center gap-2">
                        <span class="p-1 rounded-lg bg-emerald-100 text-emerald-800 text-xs">🚚</span>
                        <h3 class="text-sm font-bold text-slate-900 font-serif">Upcoming Scheduled Deliveries</h3>
                    </div>
                    <a href="#subscribersSection" onclick="document.getElementById('filter_sort').value='next_delivery';" class="text-xs font-bold text-amber-700 hover:text-amber-800">
                        View Table →
                    </a>
                </div>

                <?php if (empty($upcomingDeliveries)): ?>
                    <div class="text-center py-6 text-slate-400 italic text-xs">
                        No upcoming active deliveries scheduled currently.
                    </div>
                <?php else: ?>
                    <div class="space-y-2">
                        <?php foreach ($upcomingDeliveries as $ud): ?>
                            <div class="p-2.5 rounded-xl border border-slate-200/70 bg-slate-50/60 flex items-center justify-between gap-3 text-xs">
                                <div class="min-w-0">
                                    <strong class="text-slate-900 block truncate"><?php echo htmlspecialchars($ud['customer_name']); ?></strong>
                                    <p class="text-[11px] text-slate-500 truncate">
                                        <?php echo htmlspecialchars($ud['plan_name'] ?? 'Plan'); ?> • To: <?php echo htmlspecialchars($ud['recipient_name'] ?: 'Self'); ?> (<?php echo htmlspecialchars($ud['city'] ?: 'Bangalore'); ?>)
                                    </p>
                                </div>
                                <div class="text-right shrink-0">
                                    <span class="font-mono font-bold text-amber-800 bg-amber-100/80 border border-amber-300 px-2 py-0.5 rounded text-[11px] block">
                                        <?php echo htmlspecialchars($ud['next_delivery_date']); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Recent Subscriptions -->
            <div class="bg-white rounded-2xl border border-slate-200/80 p-5 space-y-3 shadow-xs">
                <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
                    <div class="flex items-center gap-2">
                        <span class="p-1 rounded-lg bg-amber-100 text-amber-800 text-xs">✨</span>
                        <h3 class="text-sm font-bold text-slate-900 font-serif">Recent Customer Subscriptions</h3>
                    </div>
                    <a href="#subscribersSection" class="text-xs font-bold text-amber-700 hover:text-amber-800">
                        View Table →
                    </a>
                </div>

                <?php if (empty($recentSubscriptions)): ?>
                    <div class="text-center py-6 text-slate-400 italic text-xs">
                        No subscription records created in database yet.
                    </div>
                <?php else: ?>
                    <div class="space-y-2">
                        <?php foreach ($recentSubscriptions as $rs): ?>
                            <div class="p-2.5 rounded-xl border border-slate-200/70 bg-slate-50/60 flex items-center justify-between gap-3 text-xs">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-1.5">
                                        <strong class="text-slate-900 truncate"><?php echo htmlspecialchars($rs['customer_name']); ?></strong>
                                        <span class="font-mono text-[10px] text-slate-400">#<?php echo substr($rs['id'], 0, 8); ?></span>
                                    </div>
                                    <p class="text-[11px] text-slate-500 truncate">
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


    <!-- ═══════════════════════════════════════════════════════════════════════════ -->
    <!-- SECTION 3: SUBSCRIBERS SECTION (Immediately Below Plans)                   -->
    <!-- ═══════════════════════════════════════════════════════════════════════════ -->
    <div id="subscribersSection" class="bg-white rounded-3xl border border-slate-200/80 shadow-xs p-6 space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-100 pb-4">
            <div>
                <h2 class="text-xl font-serif font-bold text-slate-900">Subscribers</h2>
                <p class="text-xs text-slate-500 mt-0.5">View customers, purchased subscription plans, delivery details, and subscription status.</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="admin.php?tab=subscriptions&action=export_csv" 
                   class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-xs font-semibold shadow-xs transition-colors">
                    📥 Export CSV
                </a>
            </div>
        </div>

        <!-- Filter & Search Controls Form -->
        <form method="GET" action="admin.php" class="bg-slate-50/70 p-4 rounded-2xl border border-slate-200/80 space-y-3">
            <input type="hidden" name="tab" value="subscriptions">

            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
                <!-- Search Box -->
                <div class="sm:col-span-2">
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-600 mb-1">Search</label>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($searchFilter); ?>" 
                           placeholder="Customer, Email, Phone, Recipient, ID..."
                           class="w-full px-3 py-1.5 rounded-xl border border-slate-300 text-xs outline-none focus:ring-2 focus:ring-amber-500 bg-white">
                </div>

                <!-- Status Filter -->
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-600 mb-1">Status</label>
                    <select name="status" class="w-full px-3 py-1.5 rounded-xl border border-slate-300 text-xs outline-none focus:ring-2 focus:ring-amber-500 bg-white">
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
                    <select name="plan_id" class="w-full px-3 py-1.5 rounded-xl border border-slate-300 text-xs outline-none focus:ring-2 focus:ring-amber-500 bg-white">
                        <option value="0">All Plans</option>
                        <?php foreach ($plans as $p): ?>
                            <option value="<?php echo $p['id']; ?>" <?php echo $planFilter === (int)$p['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($p['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Frequency Filter -->
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-600 mb-1">Frequency</label>
                    <select name="frequency" class="w-full px-3 py-1.5 rounded-xl border border-slate-300 text-xs outline-none focus:ring-2 focus:ring-amber-500 bg-white">
                        <option value="all" <?php echo $frequencyFilter === 'all' || $frequencyFilter === '' ? 'selected' : ''; ?>>All</option>
                        <option value="monthly" <?php echo $frequencyFilter === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                        <option value="quarterly" <?php echo $frequencyFilter === 'quarterly' ? 'selected' : ''; ?>>Quarterly</option>
                        <option value="yearly" <?php echo $frequencyFilter === 'yearly' ? 'selected' : ''; ?>>Yearly</option>
                    </select>
                </div>

                <!-- Occasion Filter -->
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-600 mb-1">Occasion</label>
                    <select name="occasion_type" class="w-full px-3 py-1.5 rounded-xl border border-slate-300 text-xs outline-none focus:ring-2 focus:ring-amber-500 bg-white">
                        <option value="all">All Occasions</option>
                        <?php foreach ($distinctOccasions as $occ): ?>
                            <option value="<?php echo htmlspecialchars($occ); ?>" <?php echo $occasionFilter === $occ ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($occ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Date Filters & Sorting -->
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3 pt-2 border-t border-slate-200/50">
                <!-- City Filter -->
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-600 mb-1">City</label>
                    <input type="text" name="city" value="<?php echo htmlspecialchars($cityFilter); ?>" placeholder="e.g. Bangalore"
                           class="w-full px-3 py-1.5 rounded-xl border border-slate-300 text-xs outline-none focus:ring-2 focus:ring-amber-500 bg-white">
                </div>

                <!-- Created From -->
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-600 mb-1">Created From</label>
                    <input type="date" name="created_from" value="<?php echo htmlspecialchars($createdFrom); ?>"
                           class="w-full px-3 py-1.5 rounded-xl border border-slate-300 text-xs outline-none focus:ring-2 focus:ring-amber-500 bg-white">
                </div>

                <!-- Created To -->
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-600 mb-1">Created To</label>
                    <input type="date" name="created_to" value="<?php echo htmlspecialchars($createdTo); ?>"
                           class="w-full px-3 py-1.5 rounded-xl border border-slate-300 text-xs outline-none focus:ring-2 focus:ring-amber-500 bg-white">
                </div>

                <!-- Next Delivery From -->
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-600 mb-1">Next Delivery From</label>
                    <input type="date" name="next_delivery_from" value="<?php echo htmlspecialchars($nextDeliveryFrom); ?>"
                           class="w-full px-3 py-1.5 rounded-xl border border-slate-300 text-xs outline-none focus:ring-2 focus:ring-amber-500 bg-white">
                </div>

                <!-- Sort By -->
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-600 mb-1">Sort By</label>
                    <select id="filter_sort" name="sort" class="w-full px-3 py-1.5 rounded-xl border border-slate-300 text-xs outline-none focus:ring-2 focus:ring-amber-500 bg-white">
                        <option value="newest" <?php echo $sortField === 'newest' ? 'selected' : ''; ?>>Newest Created</option>
                        <option value="oldest" <?php echo $sortField === 'oldest' ? 'selected' : ''; ?>>Oldest Created</option>
                        <option value="next_delivery" <?php echo $sortField === 'next_delivery' ? 'selected' : ''; ?>>Next Delivery Date</option>
                        <option value="occasion_date" <?php echo $sortField === 'occasion_date' ? 'selected' : ''; ?>>Occasion Date</option>
                        <option value="customer" <?php echo $sortField === 'customer' ? 'selected' : ''; ?>>Customer Name</option>
                        <option value="plan" <?php echo $sortField === 'plan' ? 'selected' : ''; ?>>Plan Name</option>
                        <option value="price_value" <?php echo $sortField === 'price_value' ? 'selected' : ''; ?>>Price / Value</option>
                        <option value="status" <?php echo $sortField === 'status' ? 'selected' : ''; ?>>Status</option>
                    </select>
                </div>

                <!-- Per Page -->
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-600 mb-1">Per Page</label>
                    <select name="per_page" class="w-full px-3 py-1.5 rounded-xl border border-slate-300 text-xs outline-none focus:ring-2 focus:ring-amber-500 bg-white">
                        <option value="10" <?php echo $perPage === 10 ? 'selected' : ''; ?>>10</option>
                        <option value="15" <?php echo $perPage === 15 ? 'selected' : ''; ?>>15</option>
                        <option value="25" <?php echo $perPage === 25 ? 'selected' : ''; ?>>25</option>
                        <option value="50" <?php echo $perPage === 50 ? 'selected' : ''; ?>>50</option>
                        <option value="100" <?php echo $perPage === 100 ? 'selected' : ''; ?>>100</option>
                    </select>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-200/60">
                <a href="admin.php?tab=subscriptions#subscribersSection" class="px-3 py-1.5 text-xs text-slate-500 hover:text-slate-800">
                    Reset Filters
                </a>
                <button type="submit" class="px-4 py-1.5 rounded-xl bg-slate-900 text-white text-xs font-bold hover:bg-slate-800 transition-colors shadow-xs">
                    Apply Filters
                </button>
            </div>
        </form>

        <!-- Subscribers Table (13 Columns) -->
        <?php if (empty($subscribers)): ?>
            <div class="text-center py-14 space-y-2">
                <div class="text-4xl">👥</div>
                <p class="text-sm font-semibold text-slate-700">No subscribers found.</p>
                <p class="text-xs text-slate-400">
                    <?php echo (!empty($searchFilter) || !empty($statusFilter) || $planFilter > 0) ? 'No subscriptions match your current filter selections.' : 'No customer subscriptions have been placed yet in the database.'; ?>
                </p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead class="bg-slate-50 text-slate-500 uppercase text-[10px] font-bold tracking-wider border-b border-slate-200">
                        <tr>
                            <th class="py-3 px-3">CUSTOMER</th>
                            <th class="py-3 px-3">EMAIL</th>
                            <th class="py-3 px-3">PHONE</th>
                            <th class="py-3 px-3">SUB ID</th>
                            <th class="py-3 px-3">PLAN</th>
                            <th class="py-3 px-3">FREQUENCY</th>
                            <th class="py-3 px-3">PRICE / VALUE</th>
                            <th class="py-3 px-3">OCCASION</th>
                            <th class="py-3 px-3">RECIPIENT</th>
                            <th class="py-3 px-3">NEXT DELIVERY</th>
                            <th class="py-3 px-3">STATUS</th>
                            <th class="py-3 px-3">CREATED</th>
                            <th class="py-3 px-4 text-right">ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($subscribers as $s): ?>
                            <tr class="hover:bg-slate-50/70 transition-colors">
                                <!-- 1. Customer -->
                                <td class="py-3.5 px-3 min-w-[130px]">
                                    <strong class="text-slate-900 font-bold block text-xs"><?php echo htmlspecialchars($s['customer_name']); ?></strong>
                                    <span class="text-[10px] text-slate-400 font-mono block">UID: <?php echo substr($s['user_id'], 0, 8); ?></span>
                                </td>

                                <!-- 2. Email -->
                                <td class="py-3.5 px-3 max-w-[140px] truncate text-slate-600" title="<?php echo htmlspecialchars($s['customer_email']); ?>">
                                    <?php echo htmlspecialchars($s['customer_email'] ?: '—'); ?>
                                </td>

                                <!-- 3. Phone -->
                                <td class="py-3.5 px-3 text-slate-600 whitespace-nowrap">
                                    <?php echo htmlspecialchars($s['customer_phone'] ?: ($s['recipient_phone'] ?: '—')); ?>
                                </td>

                                <!-- 4. Subscription ID -->
                                <td class="py-3.5 px-3 font-mono text-[11px] font-bold text-slate-700">
                                    <span class="bg-slate-100 px-1.5 py-0.5 rounded border border-slate-200">
                                        #<?php echo substr($s['id'], 0, 8); ?>
                                    </span>
                                </td>

                                <!-- 5. Plan -->
                                <td class="py-3.5 px-3 min-w-[130px]">
                                    <div class="flex items-center gap-1.5">
                                        <?php if (!empty($s['plan_image'])): ?>
                                            <img src="<?php echo htmlspecialchars($s['plan_image']); ?>" alt="Plan" class="w-6 h-6 rounded object-cover border border-slate-200 shrink-0">
                                        <?php endif; ?>
                                        <span class="font-bold text-slate-900 truncate"><?php echo htmlspecialchars($s['plan_name'] ?? 'Custom Plan'); ?></span>
                                    </div>
                                </td>

                                <!-- 6. Frequency -->
                                <td class="py-3.5 px-3 capitalize text-slate-600 whitespace-nowrap">
                                    <?php echo htmlspecialchars($s['plan_frequency'] ?? 'monthly'); ?>
                                </td>

                                <!-- 7. Price / Value -->
                                <td class="py-3.5 px-3 whitespace-nowrap">
                                    <strong class="text-amber-700 block">₹<?php echo number_format($s['plan_price_per_delivery']); ?> <span class="text-[10px] font-normal text-slate-400">/deliv</span></strong>
                                    <span class="text-[10px] text-slate-400">Total: ₹<?php echo number_format($s['plan_total_price']); ?></span>
                                </td>

                                <!-- 8. Occasion -->
                                <td class="py-3.5 px-3 whitespace-nowrap">
                                    <strong class="text-slate-800 block"><?php echo htmlspecialchars($s['occasion_type']); ?></strong>
                                    <span class="text-slate-400 text-[10px] font-mono"><?php echo htmlspecialchars($s['occasion_date']); ?></span>
                                </td>

                                <!-- 9. Recipient -->
                                <td class="py-3.5 px-3">
                                    <span class="text-slate-900 font-medium block"><?php echo htmlspecialchars($s['recipient_name'] ?: 'Self'); ?></span>
                                    <span class="text-[10px] text-slate-400 block"><?php echo htmlspecialchars($s['city'] ?: 'Bangalore'); ?></span>
                                </td>

                                <!-- 10. Next Delivery -->
                                <td class="py-3.5 px-3 whitespace-nowrap">
                                    <?php if (!empty($s['next_delivery_date'])): ?>
                                        <span class="font-mono font-bold text-amber-800 bg-amber-100/70 border border-amber-300 px-1.5 py-0.5 rounded text-[10px]">
                                            <?php echo htmlspecialchars($s['next_delivery_date']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-slate-400 italic text-[10px]">Not set</span>
                                    <?php endif; ?>
                                </td>

                                <!-- 11. Status -->
                                <td class="py-3.5 px-3 whitespace-nowrap">
                                    <?php
                                    $badgeMap = [
                                        'active'    => 'bg-emerald-100 text-emerald-800 border-emerald-300',
                                        'paused'    => 'bg-amber-100 text-amber-800 border-amber-300',
                                        'cancelled' => 'bg-rose-100 text-rose-800 border-rose-300',
                                        'expired'   => 'bg-slate-100 text-slate-700 border-slate-300',
                                    ];
                                    $bClass = $badgeMap[$s['status']] ?? 'bg-slate-100 text-slate-700 border-slate-200';
                                    ?>
                                    <span class="px-2 py-0.5 rounded-full text-[9px] font-bold uppercase tracking-wider border <?php echo $bClass; ?>">
                                        ● <?php echo htmlspecialchars($s['status']); ?>
                                    </span>
                                </td>

                                <!-- 12. Created -->
                                <td class="py-3.5 px-3 text-[10px] text-slate-400 font-mono whitespace-nowrap">
                                    <?php echo date('d M Y', strtotime($s['created_at'])); ?>
                                </td>

                                <!-- 13. Actions -->
                                <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-1">
                                        <!-- View Modal -->
                                        <button type="button" 
                                                onclick='openViewSubscriberModal(<?php echo htmlspecialchars(json_encode($s), ENT_QUOTES); ?>)'
                                                class="px-2 py-1 rounded bg-slate-100 hover:bg-slate-200 text-slate-700 text-[11px] font-semibold transition-colors">
                                            View
                                        </button>

                                        <!-- Edit Modal -->
                                        <button type="button" 
                                                onclick='openEditSubscriberModal(<?php echo htmlspecialchars(json_encode($s), ENT_QUOTES); ?>)'
                                                class="px-2 py-1 rounded bg-slate-800 hover:bg-slate-900 text-white text-[11px] font-semibold transition-colors">
                                            Edit
                                        </button>

                                        <!-- Quick Status Change Buttons -->
                                        <?php if ($s['status'] === 'active'): ?>
                                            <form method="POST" onsubmit="return confirm('Pause this subscription? Deliveries will be temporarily held.');" class="inline">
                                                <input type="hidden" name="action" value="update_subscriber_status">
                                                <input type="hidden" name="subscription_id" value="<?php echo $s['id']; ?>">
                                                <input type="hidden" name="status" value="paused">
                                                <button type="submit" class="px-1.5 py-1 text-[11px] rounded bg-amber-50 text-amber-700 hover:bg-amber-100 font-medium">
                                                    Pause
                                                </button>
                                            </form>
                                        <?php elseif ($s['status'] === 'paused'): ?>
                                            <form method="POST" onsubmit="return confirm('Resume this subscription? Deliveries will be restored.');" class="inline">
                                                <input type="hidden" name="action" value="update_subscriber_status">
                                                <input type="hidden" name="subscription_id" value="<?php echo $s['id']; ?>">
                                                <input type="hidden" name="status" value="active">
                                                <button type="submit" class="px-1.5 py-1 text-[11px] rounded bg-emerald-50 text-emerald-700 hover:bg-emerald-100 font-medium">
                                                    Resume
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if ($s['status'] !== 'cancelled'): ?>
                                            <form method="POST" onsubmit="return confirm('Cancel this subscription? Record will be retained.');" class="inline">
                                                <input type="hidden" name="action" value="update_subscriber_status">
                                                <input type="hidden" name="subscription_id" value="<?php echo $s['id']; ?>">
                                                <input type="hidden" name="status" value="cancelled">
                                                <button type="submit" class="px-1.5 py-1 text-[11px] rounded text-rose-600 hover:bg-rose-50 font-medium">
                                                    Cancel
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if ($s['status'] !== 'expired'): ?>
                                            <form method="POST" onsubmit="return confirm('Mark this subscription as expired?');" class="inline">
                                                <input type="hidden" name="action" value="update_subscriber_status">
                                                <input type="hidden" name="subscription_id" value="<?php echo $s['id']; ?>">
                                                <input type="hidden" name="status" value="expired">
                                                <button type="submit" class="px-1.5 py-1 text-[11px] rounded text-slate-500 hover:bg-slate-100 font-medium">
                                                    Expire
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

            <!-- Pagination Bar -->
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4 pt-4 border-t border-slate-100 text-xs text-slate-500">
                <div>
                    Showing <strong><?php echo min($totalSubscriberRecords, ($currentPage - 1) * $perPage + 1); ?>-<?php echo min($totalSubscriberRecords, $currentPage * $perPage); ?></strong>
                    of <strong><?php echo $totalSubscriberRecords; ?></strong> subscribers
                </div>

                <?php if ($totalPages > 1): ?>
                    <div class="flex items-center gap-1">
                        <?php
                        $basePaginationParams = http_build_query([
                            'tab'                => 'subscriptions',
                            'search'             => $searchFilter,
                            'status'             => $statusFilter,
                            'plan_id'            => $planFilter,
                            'frequency'          => $frequencyFilter,
                            'occasion_type'      => $occasionFilter,
                            'city'               => $cityFilter,
                            'created_from'       => $createdFrom,
                            'created_to'         => $createdTo,
                            'next_delivery_from' => $nextDeliveryFrom,
                            'next_delivery_to'   => $nextDeliveryTo,
                            'sort'               => $sortField,
                            'per_page'           => $perPage
                        ]);
                        ?>
                        <?php if ($currentPage > 1): ?>
                            <a href="admin.php?<?php echo $basePaginationParams; ?>&page=<?php echo $currentPage - 1; ?>#subscribersSection"
                               class="px-3 py-1.5 rounded-lg border border-slate-200 hover:bg-slate-50 font-semibold text-slate-700">
                                Previous
                            </a>
                        <?php endif; ?>

                        <?php for ($pNum = max(1, $currentPage - 2); $pNum <= min($totalPages, $currentPage + 2); $pNum++): ?>
                            <a href="admin.php?<?php echo $basePaginationParams; ?>&page=<?php echo $pNum; ?>#subscribersSection"
                               class="px-3 py-1.5 rounded-lg border font-bold <?php echo $pNum === $currentPage ? 'bg-amber-500 border-amber-500 text-slate-950' : 'border-slate-200 text-slate-700 hover:bg-slate-50'; ?>">
                                <?php echo $pNum; ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($currentPage < $totalPages): ?>
                            <a href="admin.php?<?php echo $basePaginationParams; ?>&page=<?php echo $currentPage + 1; ?>#subscribersSection"
                               class="px-3 py-1.5 rounded-lg border border-slate-200 hover:bg-slate-50 font-semibold text-slate-700">
                                Next
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>


<!-- ═══════════════════════════════════════════════════════════════════════════ -->
<!-- MODALS                                                                     -->
<!-- ═══════════════════════════════════════════════════════════════════════════ -->

<!-- 1. MODAL: ADD / EDIT SUBSCRIPTION PLAN (All 14 Fields + Product Image Upload Pipeline) -->
<div id="planFormModal" class="fixed inset-0 z-50 bg-slate-950/70 backdrop-blur-xs hidden items-center justify-center p-4">
    <div class="bg-white max-w-2xl w-full rounded-3xl p-6 shadow-2xl space-y-4 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <h3 id="planModalTitle" class="text-lg font-serif font-bold text-slate-900">Add Subscription Plan</h3>
            <button type="button" onclick="closePlanFormModal()" class="text-slate-400 hover:text-slate-600 text-lg font-bold">✕</button>
        </div>

        <form method="POST" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" id="plan_action_type" name="action" value="create_plan">
            <input type="hidden" id="modal_plan_id" name="plan_id" value="">
            <input type="hidden" id="modal_remove_image_flag" name="remove_image" value="0">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- 1. Plan Name -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Plan Name *</label>
                    <input type="text" id="modal_plan_name" name="name" required placeholder="e.g. Monthly Bloom"
                           class="w-full px-4 py-2 rounded-xl border border-slate-300 text-sm outline-none focus:ring-2 focus:ring-amber-500">
                </div>
                <!-- 2. Slug -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Slug (URL friendly)</label>
                    <input type="text" id="modal_plan_slug" name="slug" placeholder="e.g. monthly-bloom"
                           class="w-full px-4 py-2 rounded-xl border border-slate-300 text-sm outline-none focus:ring-2 focus:ring-amber-500">
                </div>
            </div>

            <!-- 3. Tagline -->
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Tagline</label>
                <input type="text" id="modal_plan_tagline" name="tagline" placeholder="e.g. Fresh curated joy, every month"
                       class="w-full px-4 py-2 rounded-xl border border-slate-300 text-sm outline-none focus:ring-2 focus:ring-amber-500">
            </div>

            <!-- 4. Description -->
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Description</label>
                <textarea id="modal_plan_description" name="description" rows="3" placeholder="Full plan description for customers..."
                          class="w-full px-4 py-2 rounded-xl border border-slate-300 text-sm outline-none focus:ring-2 focus:ring-amber-500"></textarea>
            </div>

            <!-- 5. Frequency, 6. Deliveries/Yr, 7. Price/Deliv, 8. Total Price -->
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

            <!-- 9. Savings %, 14. Display Order -->
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

            <!-- 10. Features -->
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Features (1 per line)</label>
                <textarea id="modal_plan_features" name="features" rows="3" placeholder="1 curated delivery per month&#10;Handpicked seasonal blooms&#10;Free priority delivery"
                          class="w-full px-4 py-2 rounded-xl border border-slate-300 text-sm outline-none focus:ring-2 focus:ring-amber-500"></textarea>
            </div>

            <!-- 11. Plan Image (Product Pipeline) -->
            <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200 space-y-3">
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-800">
                    Plan Cover Image (Product Image Pipeline)
                </label>
                
                <div id="modal_form_image_preview_box" class="hidden items-center gap-3">
                    <img id="modal_form_image_preview" src="" alt="Plan Preview" class="w-16 h-16 rounded-xl object-cover border border-slate-300">
                    <div>
                        <span id="modal_form_image_label" class="text-xs text-slate-600 block font-medium">Saved Cover Image</span>
                        <button type="button" onclick="markFormImageRemoval()" class="text-xs text-rose-600 hover:text-rose-800 font-semibold underline mt-0.5">
                            Remove Image
                        </button>
                    </div>
                </div>

                <div>
                    <input type="file" id="modal_form_image_file" name="plan_image" accept="image/jpeg,image/png,image/webp"
                           onchange="previewFormPlanImage(this)"
                           class="w-full px-3 py-2 rounded-xl border border-slate-300 text-xs bg-white file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-amber-100 file:text-amber-900">
                    <p class="text-[11px] text-slate-400 mt-1">Accepts JPG, PNG, WEBP (Max 5MB). Processed to 1000×1000 px square JPEG via GD.</p>
                </div>
            </div>

            <!-- 12. Is Popular, 13. Is Active -->
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
            </div>
        </form>
    </div>
</div>

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
                    <span class="text-slate-400 text-[10px] uppercase font-bold block">Total Plan Price</span>
                    <strong id="vp_total" class="text-slate-900 font-bold text-sm"></strong>
                </div>
            </div>

            <div>
                <span class="text-slate-400 text-[10px] uppercase font-bold block mb-1">Description</span>
                <p id="vp_description" class="text-slate-600 bg-slate-50 p-3 rounded-xl border border-slate-200/60"></p>
            </div>

            <div>
                <span class="text-slate-400 text-[10px] uppercase font-bold block mb-1">Included Features</span>
                <ul id="vp_features" class="list-disc pl-5 space-y-1 text-slate-600"></ul>
            </div>
        </div>

        <div class="flex justify-end pt-3 border-t border-slate-100">
            <button type="button" onclick="closeViewPlanModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl">Close</button>
        </div>
    </div>
</div>

<!-- 4. MODAL: VIEW SUBSCRIBER DETAILS (Full 6-Section Card) -->
<div id="viewSubscriberModal" class="fixed inset-0 z-50 bg-slate-950/70 backdrop-blur-xs hidden items-center justify-center p-4">
    <div class="bg-white max-w-2xl w-full rounded-3xl p-6 shadow-2xl space-y-5 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div>
                <h3 class="text-lg font-serif font-bold text-slate-900">Subscriber Details</h3>
                <span id="vs_sub_id" class="text-xs font-mono text-slate-400 font-bold"></span>
            </div>
            <button type="button" onclick="closeViewSubscriberModal()" class="text-slate-400 hover:text-slate-600 text-lg font-bold">✕</button>
        </div>

        <div class="space-y-4 text-xs text-slate-700">
            <!-- 1. Customer Information -->
            <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200/70 space-y-2">
                <span class="text-slate-500 font-bold uppercase text-[10px] tracking-wider block">1. Customer Information</span>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <span class="text-slate-400 text-[10px] block">Customer Name</span>
                        <strong id="vs_cust_name" class="text-slate-900 text-sm"></strong>
                    </div>
                    <div>
                        <span class="text-slate-400 text-[10px] block">Customer Email</span>
                        <strong id="vs_cust_email" class="text-slate-800"></strong>
                    </div>
                    <div>
                        <span class="text-slate-400 text-[10px] block">Phone Number</span>
                        <strong id="vs_cust_phone" class="text-slate-800"></strong>
                    </div>
                    <div>
                        <span class="text-slate-400 text-[10px] block">User ID</span>
                        <span id="vs_user_id" class="font-mono text-slate-600"></span>
                    </div>
                </div>
            </div>

            <!-- 2. Subscription Information -->
            <div class="bg-amber-50/30 p-4 rounded-2xl border border-amber-200/60 space-y-2">
                <span class="text-amber-800 font-bold uppercase text-[10px] tracking-wider block">2. Subscription Information</span>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <div>
                        <span class="text-slate-400 text-[10px] block">Plan Name</span>
                        <strong id="vs_plan_name" class="text-slate-900"></strong>
                    </div>
                    <div>
                        <span class="text-slate-400 text-[10px] block">Status</span>
                        <span id="vs_status" class="font-bold uppercase text-[11px]"></span>
                    </div>
                    <div>
                        <span class="text-slate-400 text-[10px] block">Frequency</span>
                        <strong id="vs_frequency" class="capitalize text-slate-800"></strong>
                    </div>
                    <div>
                        <span class="text-slate-400 text-[10px] block">Price / Deliv</span>
                        <strong id="vs_price_per_delivery" class="text-amber-700"></strong>
                    </div>
                    <div>
                        <span class="text-slate-400 text-[10px] block">Total Plan Value</span>
                        <strong id="vs_total_value" class="text-slate-900"></strong>
                    </div>
                    <div>
                        <span class="text-slate-400 text-[10px] block">Savings</span>
                        <strong id="vs_savings" class="text-emerald-700"></strong>
                    </div>
                    <div>
                        <span class="text-slate-400 text-[10px] block">Created Date</span>
                        <span id="vs_created_at" class="text-slate-600"></span>
                    </div>
                    <div>
                        <span class="text-slate-400 text-[10px] block">Updated Date</span>
                        <span id="vs_updated_at" class="text-slate-600"></span>
                    </div>
                </div>
            </div>

            <!-- 3. Occasion Information -->
            <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200/70 space-y-2">
                <span class="text-slate-500 font-bold uppercase text-[10px] tracking-wider block">3. Occasion Information</span>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <span class="text-slate-400 text-[10px] block">Occasion Type</span>
                        <strong id="vs_occasion_type" class="text-slate-900 text-sm"></strong>
                    </div>
                    <div>
                        <span class="text-slate-400 text-[10px] block">Occasion Date</span>
                        <strong id="vs_occasion_date" class="text-slate-900 font-mono"></strong>
                    </div>
                </div>
            </div>

            <!-- 4. Recipient Information -->
            <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200/70 space-y-2">
                <span class="text-slate-500 font-bold uppercase text-[10px] tracking-wider block">4. Recipient Information</span>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <span class="text-slate-400 text-[10px] block">Recipient Name</span>
                        <strong id="vs_recip_name" class="text-slate-900"></strong>
                    </div>
                    <div>
                        <span class="text-slate-400 text-[10px] block">Recipient Phone</span>
                        <strong id="vs_recip_phone" class="text-slate-900"></strong>
                    </div>
                </div>
            </div>

            <!-- 5. Delivery Information -->
            <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200/70 space-y-2">
                <span class="text-slate-500 font-bold uppercase text-[10px] tracking-wider block">5. Delivery Information</span>
                <div class="space-y-2">
                    <div>
                        <span class="text-slate-400 text-[10px] block">Delivery Address</span>
                        <p id="vs_address" class="text-slate-800 font-medium"></p>
                    </div>
                    <div class="grid grid-cols-3 gap-3 pt-1">
                        <div>
                            <span class="text-slate-400 text-[10px] block">City</span>
                            <strong id="vs_city" class="text-slate-800"></strong>
                        </div>
                        <div>
                            <span class="text-slate-400 text-[10px] block">Next Delivery Date</span>
                            <strong id="vs_next_delivery" class="text-amber-800 font-mono"></strong>
                        </div>
                        <div>
                            <span class="text-slate-400 text-[10px] block">Total Deliveries Made</span>
                            <strong id="vs_total_deliv" class="text-slate-800"></strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 6. Notes -->
            <div id="vs_notes_wrapper" class="bg-slate-50 p-4 rounded-2xl border border-slate-200/70">
                <span class="text-slate-500 font-bold uppercase text-[10px] tracking-wider block mb-1">6. Subscription Notes</span>
                <p id="vs_notes" class="text-slate-700 italic"></p>
            </div>
        </div>

        <div class="flex justify-end pt-3 border-t border-slate-100">
            <button type="button" onclick="closeViewSubscriberModal()" class="px-5 py-2 bg-slate-900 hover:bg-slate-800 text-white text-xs font-semibold rounded-xl">
                Close
            </button>
        </div>
    </div>
</div>

<!-- 5. MODAL: EDIT SUBSCRIBER -->
<div id="editSubscriberModal" class="fixed inset-0 z-50 bg-slate-950/70 backdrop-blur-xs hidden items-center justify-center p-4">
    <div class="bg-white max-w-xl w-full rounded-3xl p-6 shadow-2xl space-y-4 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <h3 class="text-lg font-serif font-bold text-slate-900">Edit Subscriber Record</h3>
            <button type="button" onclick="closeEditSubscriberModal()" class="text-slate-400 hover:text-slate-600 text-lg font-bold">✕</button>
        </div>

        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="edit_subscriber">
            <input type="hidden" id="es_sub_id" name="subscription_id" value="">

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Plan</label>
                    <select id="es_plan_id" name="plan_id" class="w-full px-3 py-2 rounded-xl border border-slate-300 text-xs bg-white">
                        <?php foreach ($plans as $p): ?>
                            <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Status</label>
                    <select id="es_status" name="status" class="w-full px-3 py-2 rounded-xl border border-slate-300 text-xs bg-white">
                        <option value="active">Active</option>
                        <option value="paused">Paused</option>
                        <option value="cancelled">Cancelled</option>
                        <option value="expired">Expired</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Recipient Name</label>
                    <input type="text" id="es_recipient_name" name="recipient_name" class="w-full px-3 py-2 rounded-xl border border-slate-300 text-xs">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Recipient Phone</label>
                    <input type="text" id="es_recipient_phone" name="recipient_phone" class="w-full px-3 py-2 rounded-xl border border-slate-300 text-xs">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Occasion Type</label>
                    <input type="text" id="es_occasion_type" name="occasion_type" class="w-full px-3 py-2 rounded-xl border border-slate-300 text-xs">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Occasion Date</label>
                    <input type="date" id="es_occasion_date" name="occasion_date" class="w-full px-3 py-2 rounded-xl border border-slate-300 text-xs">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Delivery Address</label>
                <textarea id="es_delivery_address" name="delivery_address" rows="2" class="w-full px-3 py-2 rounded-xl border border-slate-300 text-xs"></textarea>
            </div>

            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">City</label>
                    <input type="text" id="es_city" name="city" class="w-full px-3 py-2 rounded-xl border border-slate-300 text-xs">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Next Delivery</label>
                    <input type="date" id="es_next_delivery" name="next_delivery_date" class="w-full px-3 py-2 rounded-xl border border-slate-300 text-xs">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Deliveries Count</label>
                    <input type="number" id="es_total_deliveries" name="total_deliveries" min="0" class="w-full px-3 py-2 rounded-xl border border-slate-300 text-xs">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Notes</label>
                <textarea id="es_notes" name="notes" rows="2" class="w-full px-3 py-2 rounded-xl border border-slate-300 text-xs"></textarea>
            </div>

            <div class="flex justify-end gap-3 pt-3 border-t border-slate-100">
                <button type="button" onclick="closeEditSubscriberModal()" class="px-4 py-2 text-slate-600 text-xs font-semibold">Cancel</button>
                <button type="submit" class="px-5 py-2 gold-gradient text-slate-950 font-bold rounded-xl text-xs shadow-md">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>


<!-- ═══════════════════════════════════════════════════════════════════════════ -->
<!-- JAVASCRIPT CONTROLLERS                                                     -->
<!-- ═══════════════════════════════════════════════════════════════════════════ -->
<script>
    // ── ADD / EDIT PLAN MODAL ────────────────────────────────────────────────
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
        document.getElementById('modal_plan_order').value = '<?php echo count($plans) + 1; ?>';
        document.getElementById('modal_plan_features').value = '';
        document.getElementById('modal_plan_popular').checked = false;
        document.getElementById('modal_plan_active').checked = true;
        document.getElementById('modal_remove_image_flag').value = '0';
        document.getElementById('modal_form_image_file').value = '';
        document.getElementById('modal_form_image_preview_box').classList.add('hidden');

        document.getElementById('planFormModal').classList.remove('hidden');
        document.getElementById('planFormModal').classList.add('flex');
    }

    function openEditPlanModal(plan) {
        document.getElementById('planModalTitle').textContent = 'Edit Subscription Plan: ' + plan.name;
        document.getElementById('plan_action_type').value = 'edit_plan';
        document.getElementById('modal_plan_id').value = plan.id;
        document.getElementById('modal_plan_name').value = plan.name;
        document.getElementById('modal_plan_slug').value = plan.slug;
        document.getElementById('modal_plan_tagline').value = plan.tagline || '';
        document.getElementById('modal_plan_description').value = plan.description || '';
        document.getElementById('modal_plan_frequency').value = plan.frequency;
        document.getElementById('modal_plan_deliveries').value = plan.deliveries_per_year;
        document.getElementById('modal_plan_price').value = plan.price_per_delivery;
        document.getElementById('modal_plan_total').value = plan.total_price;
        document.getElementById('modal_plan_savings').value = plan.savings_percent;
        document.getElementById('modal_plan_order').value = plan.display_order;
        document.getElementById('modal_plan_popular').checked = Boolean(Number(plan.is_popular));
        document.getElementById('modal_plan_active').checked = Boolean(Number(plan.is_active));
        document.getElementById('modal_remove_image_flag').value = '0';
        document.getElementById('modal_form_image_file').value = '';

        // Features parsing
        let feats = [];
        try {
            feats = JSON.parse(plan.features);
            if (!Array.isArray(feats) && feats.features) feats = feats.features;
        } catch (e) {
            feats = plan.features ? [plan.features] : [];
        }
        document.getElementById('modal_plan_features').value = Array.isArray(feats) ? feats.join("\n") : '';

        // Existing image preview
        if (plan.image) {
            document.getElementById('modal_form_image_preview').src = plan.image;
            document.getElementById('modal_form_image_label').textContent = 'Current Cover Image';
            document.getElementById('modal_form_image_preview_box').classList.remove('hidden');
            document.getElementById('modal_form_image_preview_box').classList.add('flex');
        } else {
            document.getElementById('modal_form_image_preview_box').classList.add('hidden');
            document.getElementById('modal_form_image_preview_box').classList.remove('flex');
        }

        document.getElementById('planFormModal').classList.remove('hidden');
        document.getElementById('planFormModal').classList.add('flex');
    }

    function closePlanFormModal() {
        document.getElementById('planFormModal').classList.add('hidden');
        document.getElementById('planFormModal').classList.remove('flex');
    }

    function previewFormPlanImage(input) {
        if (!input.files || !input.files[0]) return;
        const file = input.files[0];
        const allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
        if (!allowed.includes(file.type)) {
            alert('Invalid file format. Please upload JPG, PNG, or WEBP.');
            input.value = '';
            return;
        }
        if (file.size > 5 * 1024 * 1024) {
            alert('File exceeds 5 MB size limit.');
            input.value = '';
            return;
        }

        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('modal_form_image_preview').src = e.target.result;
            document.getElementById('modal_form_image_label').textContent = 'Selected: ' + file.name + ' (1000×1000 crop on save)';
            document.getElementById('modal_form_image_preview_box').classList.remove('hidden');
            document.getElementById('modal_form_image_preview_box').classList.add('flex');
            document.getElementById('modal_remove_image_flag').value = '0';
        };
        reader.readAsDataURL(file);
    }

    function markFormImageRemoval() {
        if (!confirm('Remove this image? It will be deleted from storage when you save.')) return;
        document.getElementById('modal_form_image_preview').src = '';
        document.getElementById('modal_form_image_preview_box').classList.add('hidden');
        document.getElementById('modal_form_image_preview_box').classList.remove('flex');
        document.getElementById('modal_form_image_file').value = '';
        document.getElementById('modal_remove_image_flag').value = '1';
    }

    // Auto-calculate Total Price based on Frequency and Price/Delivery
    document.getElementById('modal_plan_price').addEventListener('input', updatePlanTotal);
    document.getElementById('modal_plan_deliveries').addEventListener('input', updatePlanTotal);
    document.getElementById('modal_plan_frequency').addEventListener('change', function() {
        const delivInput = document.getElementById('modal_plan_deliveries');
        if (this.value === 'monthly') delivInput.value = '12';
        else if (this.value === 'quarterly') delivInput.value = '4';
        else if (this.value === 'yearly') delivInput.value = '1';
        updatePlanTotal();
    });

    function updatePlanTotal() {
        const price = parseFloat(document.getElementById('modal_plan_price').value) || 0;
        const deliv = parseInt(document.getElementById('modal_plan_deliveries').value) || 1;
        const total = document.getElementById('modal_plan_total');
        if (!total.dataset.userEdited) {
            total.value = (price * deliv).toFixed(2);
        }
    }

    // ── DEDICATED IMAGE MODAL ────────────────────────────────────────────────
    function openPlanImageModal(plan) {
        document.getElementById('img_modal_plan_id').value = plan.id;
        document.getElementById('imageModalPlanName').textContent = plan.name + ' (' + plan.frequency + ')';
        document.getElementById('remove_image_flag').value = '0';
        document.getElementById('plan_image_input').value = '';

        if (plan.image) {
            document.getElementById('imagePreview').src = plan.image;
            document.getElementById('imagePreviewWrapper').classList.remove('hidden');
            document.getElementById('noImagePlaceholder').classList.add('hidden');
            document.getElementById('previewLabel').textContent = 'Current saved image';
        } else {
            document.getElementById('imagePreviewWrapper').classList.add('hidden');
            document.getElementById('noImagePlaceholder').classList.remove('hidden');
            document.getElementById('previewLabel').textContent = 'No image set';
        }

        document.getElementById('planImageModal').classList.remove('hidden');
        document.getElementById('planImageModal').classList.add('flex');
    }

    function closePlanImageModal() {
        document.getElementById('planImageModal').classList.add('hidden');
        document.getElementById('planImageModal').classList.remove('flex');
    }

    function previewPlanImage(input) {
        if (!input.files || !input.files[0]) return;
        const file = input.files[0];

        const allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
        if (!allowed.includes(file.type)) {
            alert('Invalid file format. Please upload JPG, PNG, or WEBP.');
            input.value = '';
            return;
        }
        if (file.size > 5 * 1024 * 1024) {
            alert('File exceeds 5 MB size limit.');
            input.value = '';
            return;
        }

        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('imagePreview').src = e.target.result;
            document.getElementById('imagePreviewWrapper').classList.remove('hidden');
            document.getElementById('noImagePlaceholder').classList.add('hidden');
            document.getElementById('previewLabel').textContent = 'Selected: ' + file.name + ' (will be cropped to 1000×1000)';
            document.getElementById('remove_image_flag').value = '0';
        };
        reader.readAsDataURL(file);
    }

    function removeImage() {
        if (!confirm('Remove this image? It will be deleted from storage when you save.')) return;
        document.getElementById('imagePreview').src = '';
        document.getElementById('imagePreviewWrapper').classList.add('hidden');
        document.getElementById('noImagePlaceholder').classList.remove('hidden');
        document.getElementById('plan_image_input').value = '';
        document.getElementById('remove_image_flag').value = '1';
        document.getElementById('previewLabel').textContent = 'Image marked for removal';
    }

    // ── VIEW PLAN MODAL ──────────────────────────────────────────────────────
    function openViewPlanModal(plan) {
        document.getElementById('vp_title').textContent = plan.name;
        document.getElementById('vp_image').src = plan.image || 'https://images.unsplash.com/photo-1561181286-d3fee7d55364?auto=format&fit=crop&w=600&q=80';
        document.getElementById('vp_frequency').textContent = plan.frequency;
        document.getElementById('vp_deliveries').textContent = plan.deliveries_per_year + ' deliveries / year';
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
            feats = plan.features ? [plan.features] : [];
        }
        if (Array.isArray(feats) && feats.length > 0) {
            feats.forEach(f => {
                if (f && f.trim()) {
                    const li = document.createElement('li');
                    li.textContent = f;
                    fList.appendChild(li);
                }
            });
        } else {
            const li = document.createElement('li');
            li.textContent = 'Standard curated floral arrangements';
            fList.appendChild(li);
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
        document.getElementById('vs_sub_id').textContent = 'Subscription ID: #' + s.id;
        document.getElementById('vs_cust_name').textContent = s.customer_name || 'Customer';
        document.getElementById('vs_cust_email').textContent = s.customer_email || '—';
        document.getElementById('vs_cust_phone').textContent = s.customer_phone || s.recipient_phone || '—';
        document.getElementById('vs_user_id').textContent = s.user_id || '—';

        document.getElementById('vs_plan_name').textContent = s.plan_name || 'Custom Plan';
        document.getElementById('vs_status').textContent = '● ' + (s.status || 'active');
        document.getElementById('vs_status').className = 'font-bold uppercase text-[11px] ' + 
            (s.status === 'active' ? 'text-emerald-700' : (s.status === 'paused' ? 'text-amber-700' : 'text-rose-700'));
        document.getElementById('vs_frequency').textContent = s.plan_frequency || 'monthly';
        document.getElementById('vs_price_per_delivery').textContent = '₹' + Number(s.plan_price_per_delivery || 0).toLocaleString('en-IN');
        document.getElementById('vs_total_value').textContent = '₹' + Number(s.plan_total_price || 0).toLocaleString('en-IN');
        document.getElementById('vs_savings').textContent = (s.plan_savings_percent || 0) + '%';
        document.getElementById('vs_created_at').textContent = s.created_at || '—';
        document.getElementById('vs_updated_at').textContent = s.updated_at || '—';

        document.getElementById('vs_occasion_type').textContent = s.occasion_type || 'Special Occasion';
        document.getElementById('vs_occasion_date').textContent = s.occasion_date || '—';

        document.getElementById('vs_recip_name').textContent = s.recipient_name || s.customer_name || '—';
        document.getElementById('vs_recip_phone').textContent = s.recipient_phone || s.customer_phone || '—';
        document.getElementById('vs_address').textContent = s.delivery_address || 'No street address specified';
        document.getElementById('vs_city').textContent = s.city || 'Bangalore';
        document.getElementById('vs_next_delivery').textContent = s.next_delivery_date || 'Not Scheduled';
        document.getElementById('vs_total_deliv').textContent = (s.total_deliveries || 0) + ' deliveries';

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
    });
</script>

<?php require_once 'admin_footer.php'; ?>
