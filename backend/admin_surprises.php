<?php
// ADMIN PANEL: SURPRISE EXPERIENCES & PINCODES MANAGEMENT
require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$database = new Database();
$db = $database->getConnection();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin.php");
    exit();
}

// Ensure database tables exist
ensureSurpriseTablesExist($db);

$message = "";
$error = "";

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

if (!function_exists('saveExperienceImage')) {
    /**
     * Preserves original 16:9 / horizontal aspect ratio without square cropping.
     * Only scales down if image is exceedingly massive (> 1920px width).
     */
    function saveExperienceImage(string $tmpName, string $destPath, string $ext): bool
    {
        if (extension_loaded('gd')) {
            $info = @getimagesize($tmpName);
            if ($info) {
                [$srcW, $srcH, $imgType] = [$info[0], $info[1], $info[2]];
                
                // If image is within 1920px, keep original file directly
                if ($srcW <= 1920 && $srcH <= 1200) {
                    if (@move_uploaded_file($tmpName, $destPath)) {
                        return true;
                    }
                    return @copy($tmpName, $destPath);
                }

                // Downscale proportionally (never crop)
                $maxDim = 1920;
                if ($srcW > $srcH) {
                    $dstW = $maxDim;
                    $dstH = (int)round(($srcH / $srcW) * $maxDim);
                } else {
                    $dstH = $maxDim;
                    $dstW = (int)round(($srcW / $srcH) * $maxDim);
                }

                switch ($imgType) {
                    case IMAGETYPE_JPEG: $src = @imagecreatefromjpeg($tmpName); break;
                    case IMAGETYPE_PNG:  $src = @imagecreatefrompng($tmpName);  break;
                    case IMAGETYPE_WEBP: $src = @imagecreatefromwebp($tmpName); break;
                    default: $src = false;
                }

                if ($src) {
                    $dst = imagecreatetruecolor($dstW, $dstH);
                    if ($imgType === IMAGETYPE_PNG || $imgType === IMAGETYPE_WEBP) {
                        imagealphablending($dst, false);
                        imagesavealpha($dst, true);
                        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
                        imagefill($dst, 0, 0, $transparent);
                    }
                    imagecopyresampled($dst, $src, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);

                    $saved = false;
                    if ($ext === 'png') {
                        $saved = @imagepng($dst, $destPath, 8);
                    } elseif ($ext === 'webp') {
                        $saved = @imagewebp($dst, $destPath, 90);
                    } else {
                        $saved = @imagejpeg($dst, $destPath, 90);
                    }
                    imagedestroy($src);
                    imagedestroy($dst);
                    if ($saved) return true;
                }
            }
        }

        // Direct save fallback
        if (@move_uploaded_file($tmpName, $destPath)) {
            return true;
        }
        return @copy($tmpName, $destPath);
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
        return empty($text) ? 'experience' : $text;
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

/**
 * processSingleExperienceUpload()
 * Saves a single uploaded 16:9 widescreen experience image up to 8MB.
 */
function processSingleExperienceUpload(array $file, string $title = '', int $index = 1): string
{
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return '';
    }

    $size = $file['size'];
    $tmpName = $file['tmp_name'];
    $origName = $file['name'];

    if ($size > 8 * 1024 * 1024) {
        throw new Exception("File {$origName} is too large. Maximum allowed size is 8 MB.");
    }

    $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($tmpName);
    $allowedMimes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
    if (!in_array($mimeType, $allowedMimes, true)) {
        throw new Exception("Invalid file type for {$origName}. Only JPG, JPEG, PNG, and WEBP are allowed.");
    }

    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
    if (!in_array($ext, $allowedExts, true)) {
        throw new Exception("Invalid file extension for {$origName}. Only .jpg, .jpeg, .png, and .webp are permitted.");
    }

    $primaryDir   = OMG_PRIMARY_DIR;
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

    $suffix = 'slide' . $index . '-' . substr(md5(uniqid((string)mt_rand(), true)), 0, 6);
    $targetFilename = generateProductImageFilename(
        !empty($title) ? $title : 'surprise-experience',
        $suffix,
        $ext
    );

    $primaryTarget   = $primaryDir   . $targetFilename;
    $secondaryTarget = $secondaryDir . $targetFilename;

    if (!saveExperienceImage($tmpName, $primaryTarget, $ext)) {
        throw new Exception("Failed to process and save image: {$origName}");
    }

    if (is_writable($secondaryDir) && !copy($primaryTarget, $secondaryTarget)) {
        error_log('[OMG Upload] copy() to backend/uploads/ failed for experience image: ' . $targetFilename);
    }

    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    return $protocol . '://' . $_SERVER['HTTP_HOST'] . OMG_IMG_URL_PATH . $targetFilename;
}

function handleExperienceImageUpload(string $fileKey, string $title = '', string $existingUrl = ''): string
{
    if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
        return $existingUrl;
    }
    return processSingleExperienceUpload($_FILES[$fileKey], $title, 1);
}

// ── CRUD HANDLERS ─────────────────────────────────────────────────────────

// 1. Save / Update Experience (Supports Multiple 16:9 Images for 10-Second Auto-Rolling Slideshow)
if (isset($_POST['action']) && $_POST['action'] === 'save_experience') {
    $id = $_POST['id'] ?? null;
    $title = trim($_POST['title'] ?? '');
    $subtitle = trim($_POST['subtitle'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $badge = trim($_POST['badge'] ?? '');
    $base_price = (float) ($_POST['base_price'] ?? 0);
    $existing_images_raw = trim($_POST['existing_images_json'] ?? '');
    $existing_image_fallback = trim($_POST['existing_image'] ?? '');
    $remove_image = isset($_POST['remove_image']) && $_POST['remove_image'] === '1';
    $features_raw = trim($_POST['features'] ?? '');
    $display_order = (int) ($_POST['display_order'] ?? 0);
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    $existing_images = [];
    if (!$remove_image) {
        if (!empty($existing_images_raw)) {
            $decodedEx = json_decode($existing_images_raw, true);
            if (is_array($decodedEx)) {
                $existing_images = array_values(array_filter($decodedEx));
            }
        }
        if (empty($existing_images) && !empty($existing_image_fallback)) {
            $existing_images = [$existing_image_fallback];
        }
    }

    $uploaded_images = [];
    try {
        // Multi-file upload
        if (isset($_FILES['image_files']['name']) && is_array($_FILES['image_files']['name'])) {
            $fileCount = count($_FILES['image_files']['name']);
            for ($i = 0; $i < $fileCount; $i++) {
                if ($_FILES['image_files']['error'][$i] === UPLOAD_ERR_OK) {
                    $singleFile = [
                        'name'     => $_FILES['image_files']['name'][$i],
                        'type'     => $_FILES['image_files']['type'][$i],
                        'tmp_name' => $_FILES['image_files']['tmp_name'][$i],
                        'error'    => $_FILES['image_files']['error'][$i],
                        'size'     => $_FILES['image_files']['size'][$i],
                    ];
                    $newUrl = processSingleExperienceUpload($singleFile, $title, count($uploaded_images) + 1);
                    if ($newUrl) {
                        $uploaded_images[] = $newUrl;
                    }
                }
            }
        } elseif (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
            $newUrl = processSingleExperienceUpload($_FILES['image_file'], $title, 1);
            if ($newUrl) {
                $uploaded_images[] = $newUrl;
            }
        }

        // Direct URLs (optional)
        $direct_urls_raw = trim($_POST['image_urls'] ?? ($_POST['image_url'] ?? ''));
        $direct_urls = [];
        if (!empty($direct_urls_raw)) {
            $urlLines = preg_split('/[\r\n,]+/', $direct_urls_raw);
            foreach ($urlLines as $line) {
                $trimmedLine = trim($line);
                if (!empty($trimmedLine) && filter_var($trimmedLine, FILTER_VALIDATE_URL)) {
                    $direct_urls[] = $trimmedLine;
                }
            }
        }

        $all_images = array_merge($existing_images, $uploaded_images, $direct_urls);
        $all_images = array_values(array_unique(array_filter($all_images)));

        $image = !empty($all_images) ? $all_images[0] : '';
        $images_json = json_encode($all_images);
    } catch (Exception $e) {
        $error = $e->getMessage();
    }

    // Convert features to JSON array
    $featureList = array_values(array_filter(array_map('trim', explode("\n", $features_raw))));
    $features_json = json_encode($featureList);

    if (empty($error)) {
        if ($title && $image) {
            if ($id) {
                $stmt = $db->prepare("UPDATE `surprise_experiences` SET `title` = :title, `subtitle` = :subtitle, `description` = :description, `badge` = :badge, `base_price` = :base_price, `image` = :image, `images` = :images, `features` = :features, `display_order` = :display_order, `is_active` = :is_active WHERE `id` = :id");
                $stmt->execute([
                    ':title' => $title,
                    ':subtitle' => $subtitle,
                    ':description' => $description,
                    ':badge' => $badge,
                    ':base_price' => $base_price,
                    ':image' => $image,
                    ':images' => $images_json,
                    ':features' => $features_json,
                    ':display_order' => $display_order,
                    ':is_active' => $is_active,
                    ':id' => $id
                ]);
                $message = "Base Experience updated successfully with " . count($all_images) . " image(s)!";
            } else {
                $stmt = $db->prepare("INSERT INTO `surprise_experiences` (`title`, `subtitle`, `description`, `badge`, `base_price`, `image`, `images`, `features`, `display_order`, `is_active`) VALUES (:title, :subtitle, :description, :badge, :base_price, :image, :images, :features, :display_order, :is_active)");
                $stmt->execute([
                    ':title' => $title,
                    ':subtitle' => $subtitle,
                    ':description' => $description,
                    ':badge' => $badge,
                    ':base_price' => $base_price,
                    ':image' => $image,
                    ':images' => $images_json,
                    ':features' => $features_json,
                    ':display_order' => $display_order,
                    ':is_active' => $is_active
                ]);
                $message = "New Base Experience created successfully with " . count($all_images) . " image(s)!";
            }
        } else {
            $error = "Title and at least one 16:9 Image are required.";
        }
    }
}

// 2. Delete Experience
if (isset($_POST['action']) && $_POST['action'] === 'delete_experience') {
    $id = $_POST['id'] ?? null;
    if ($id) {
        $stmtImg = $db->prepare("SELECT `image`, `images` FROM `surprise_experiences` WHERE `id` = :id");
        $stmtImg->execute([':id' => $id]);
        $row = $stmtImg->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $imgsToDelete = [];
            if (!empty($row['images'])) {
                $decoded = json_decode($row['images'], true);
                if (is_array($decoded)) $imgsToDelete = $decoded;
            }
            if (!empty($row['image'])) {
                $imgsToDelete[] = $row['image'];
            }
            foreach (array_unique($imgsToDelete) as $delImg) {
                if ($delImg && strpos($delImg, OMG_IMG_URL_PATH) !== false) {
                    deleteLocalImage($delImg);
                }
            }
        }
        $stmt = $db->prepare("DELETE FROM `surprise_experiences` WHERE `id` = :id");
        $stmt->execute([':id' => $id]);
        $message = "Experience deleted successfully.";
    }
}

// 3. Save / Update Upgrade
if (isset($_POST['action']) && $_POST['action'] === 'save_upgrade') {
    $id = $_POST['id'] ?? null;
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $icon = trim($_POST['icon'] ?? 'Sparkles');
    $price = (float) ($_POST['price'] ?? 0);
    $display_order = (int) ($_POST['display_order'] ?? 0);
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if ($name) {
        if ($id) {
            $stmt = $db->prepare("UPDATE `surprise_upgrades` SET `name` = :name, `description` = :description, `icon` = :icon, `price` = :price, `display_order` = :display_order, `is_active` = :is_active WHERE `id` = :id");
            $stmt->execute([
                ':name' => $name,
                ':description' => $description,
                ':icon' => $icon,
                ':price' => $price,
                ':display_order' => $display_order,
                ':is_active' => $is_active,
                ':id' => $id
            ]);
            $message = "Experience Upgrade updated successfully!";
        } else {
            $stmt = $db->prepare("INSERT INTO `surprise_upgrades` (`name`, `description`, `icon`, `price`, `display_order`, `is_active`) VALUES (:name, :description, :icon, :price, :display_order, :is_active)");
            $stmt->execute([
                ':name' => $name,
                ':description' => $description,
                ':icon' => $icon,
                ':price' => $price,
                ':display_order' => $display_order,
                ':is_active' => $is_active
            ]);
            $message = "New Experience Upgrade added successfully!";
        }
    } else {
        $error = "Upgrade Name is required.";
    }
}

// 4. Delete Upgrade
if (isset($_POST['action']) && $_POST['action'] === 'delete_upgrade') {
    $id = $_POST['id'] ?? null;
    if ($id) {
        $stmt = $db->prepare("DELETE FROM `surprise_upgrades` WHERE `id` = :id");
        $stmt->execute([':id' => $id]);
        $message = "Upgrade deleted successfully.";
    }
}

// Fetch all data for display
$experiences = $db->query("SELECT * FROM `surprise_experiences` ORDER BY `display_order` ASC, `id` ASC")->fetchAll(PDO::FETCH_ASSOC);
$upgrades = $db->query("SELECT * FROM `surprise_upgrades` ORDER BY `display_order` ASC, `id` ASC")->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Surprise Experience Builder & Google Maps Pincodes";
require_once 'admin_header.php';
?>

<div class="space-y-8">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-200 pb-6">
        <div>
            <h1 class="text-2xl sm:text-3xl font-serif font-bold text-slate-900">Surprise Experience & Area Control</h1>
            <p class="text-slate-500 text-sm mt-1">Manage base surprise packages, upgrades, and pricing</p>
        </div>
    </div>

    <!-- Alert Messages -->
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

    <!-- SECTION 1: BASE SURPRISE EXPERIENCES -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 space-y-6">
        <div
            class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 border-b border-slate-100 pb-4">
            <div>
                <h3 class="text-lg font-serif font-bold text-slate-900">1. Base Surprise Experiences</h3>
                <p class="text-xs text-slate-500">Add or edit base surprise packages, titles, pricing, badges and
                    images.</p>
            </div>
            <button onclick="openExpModal()"
                class="py-2 px-4 gold-gradient text-slate-950 font-bold rounded-xl text-xs shadow-md hover:shadow-lg transition-all">
                + Add New Experience
            </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <?php foreach ($experiences as $exp): ?>
                <?php
                $feats = json_decode($exp['features'], true);
                $featsList = is_array($feats) ? implode(', ', $feats) : $exp['features'];
                ?>
                <div
                    class="border border-slate-200 rounded-xl p-5 bg-slate-50/50 flex flex-col justify-between space-y-4 hover:border-amber-400 transition-colors">
                    <div class="space-y-3">
                        <div class="flex justify-between items-start gap-2">
                            <div>
                                <span
                                    class="px-2 py-0.5 text-[10px] uppercase font-bold tracking-wider rounded bg-slate-200 text-slate-700">Order
                                    #<?php echo $exp['display_order']; ?></span>
                                <?php if ($exp['badge']): ?>
                                    <span
                                        class="px-2 py-0.5 text-[10px] font-bold rounded bg-amber-100 text-amber-800 border border-amber-200 ml-1"><?php echo htmlspecialchars($exp['badge']); ?></span>
                                <?php endif; ?>
                                <h4 class="font-serif font-bold text-base text-slate-900 mt-1">
                                    <?php echo htmlspecialchars($exp['title']); ?>
                                </h4>
                            </div>
                            <span
                                class="text-xs px-2.5 py-0.5 rounded-full bg-amber-50 text-amber-800 font-semibold border border-amber-200">Bespoke Experience</span>
                        </div>
                        <p class="text-xs text-slate-500 italic"><?php echo htmlspecialchars($exp['subtitle']); ?></p>
                        <p class="text-xs text-slate-600 line-clamp-2"><?php echo htmlspecialchars($exp['description']); ?>
                        </p>

                        <?php
                        $expImages = [];
                        if (!empty($exp['images'])) {
                            $decoded = json_decode($exp['images'], true);
                            if (is_array($decoded)) $expImages = array_filter($decoded);
                        }
                        if (empty($expImages) && !empty($exp['image'])) {
                            $expImages = [$exp['image']];
                        }
                        $imgCount = count($expImages);
                        ?>
                        <div class="flex items-center gap-3 pt-2">
                            <div class="relative shrink-0">
                                <img src="<?php echo htmlspecialchars($exp['image']); ?>"
                                    class="w-20 h-12 object-cover rounded-lg border border-slate-200" alt="Exp Image">
                                <?php if ($imgCount > 1): ?>
                                    <span class="absolute -top-1.5 -right-1.5 px-1.5 py-0.2 rounded-full text-[9px] font-bold bg-amber-500 text-black shadow-xs">
                                        <?php echo $imgCount; ?> imgs
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="text-[11px] text-slate-500 min-w-0 flex-1">
                                <?php if ($imgCount > 1): ?>
                                    <span class="text-amber-800 font-bold block mb-0.5">🔄 <?php echo $imgCount; ?> Images (10s auto-roll)</span>
                                <?php endif; ?>
                                <strong class="text-slate-700 block mb-0.5">Features:</strong>
                                <span class="truncate block"><?php echo htmlspecialchars($featsList); ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between pt-3 border-t border-slate-200/60 text-xs">
                        <span
                            class="<?php echo $exp['is_active'] ? 'text-emerald-600 font-semibold' : 'text-slate-400'; ?>">
                            <?php echo $exp['is_active'] ? '● Active' : '○ Disabled'; ?>
                        </span>
                        <div class="flex items-center gap-2">
                            <button onclick='editExp(<?php echo json_encode($exp); ?>)'
                                class="px-3 py-1 bg-slate-800 text-white rounded-lg font-medium hover:bg-slate-900">Edit</button>
                            <form method="POST" onsubmit="return confirm('Delete this experience?');" class="inline">
                                <input type="hidden" name="action" value="delete_experience">
                                <input type="hidden" name="id" value="<?php echo $exp['id']; ?>">
                                <button type="submit"
                                    class="px-3 py-1 bg-rose-50 text-rose-600 rounded-lg font-medium hover:bg-rose-100">Delete</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- SECTION 2: EXPERIENCE UPGRADES -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 space-y-6">
        <div
            class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 border-b border-slate-100 pb-4">
            <div>
                <h3 class="text-lg font-serif font-bold text-slate-900">2. Experience Upgrades & Add-ons</h3>
                <p class="text-xs text-slate-500">Configure optional add-on experiences, icons, descriptions & prices.
                </p>
            </div>
            <button onclick="openUpgModal()"
                class="py-2 px-4 gold-gradient text-slate-950 font-bold rounded-xl text-xs shadow-md hover:shadow-lg transition-all">
                + Add New Upgrade
            </button>
        </div>

        <div class="table-wrapper">
            <table class="w-full text-left text-sm">
                <thead
                    class="bg-slate-100/70 text-slate-600 uppercase text-[11px] font-bold tracking-wider border-b border-slate-200">
                    <tr>
                        <th class="py-3 px-4">Order</th>
                        <th class="py-3 px-4">Icon</th>
                        <th class="py-3 px-4">Upgrade Name</th>
                        <th class="py-3 px-4">Description</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($upgrades as $upg): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="py-3.5 px-4 text-xs font-mono font-bold text-slate-500">
                                #<?php echo $upg['display_order']; ?></td>
                            <td class="py-3.5 px-4 font-semibold text-amber-600 text-xs">
                                <?php echo htmlspecialchars($upg['icon']); ?>
                            </td>
                            <td class="py-3.5 px-4 font-bold text-slate-900"><?php echo htmlspecialchars($upg['name']); ?>
                            </td>
                            <td class="py-3.5 px-4 text-xs text-slate-600 max-w-xs">
                                <?php echo htmlspecialchars($upg['description']); ?>
                            </td>
                            <td class="py-3.5 px-4 text-xs">
                                <span
                                    class="<?php echo $upg['is_active'] ? 'text-emerald-600 font-semibold' : 'text-slate-400'; ?>">
                                    <?php echo $upg['is_active'] ? 'Active' : 'Disabled'; ?>
                                </span>
                            </td>
                            <td class="py-3.5 px-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button onclick='editUpg(<?php echo json_encode($upg); ?>)'
                                        class="px-2.5 py-1 bg-slate-800 text-white rounded text-xs font-medium">Edit</button>
                                    <form method="POST" onsubmit="return confirm('Delete this upgrade?');" class="inline">
                                        <input type="hidden" name="action" value="delete_upgrade">
                                        <input type="hidden" name="id" value="<?php echo $upg['id']; ?>">
                                        <button type="submit"
                                            class="px-2.5 py-1 bg-rose-50 text-rose-600 rounded text-xs font-medium">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>


</div>

<!-- MODAL: ADD/EDIT EXPERIENCE -->
<div id="expModal"
    class="hidden fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="bg-white max-w-lg w-full rounded-2xl p-6 shadow-2xl space-y-4 max-h-[90vh] overflow-y-auto">
        <h3 id="expModalTitle" class="text-lg font-serif font-bold text-slate-900 border-b border-slate-100 pb-3">Add
            Experience</h3>
        <form method="POST" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="action" value="save_experience">
            <input type="hidden" id="exp_id" name="id" value="">
            <input type="hidden" id="exp_existing_image" name="existing_image" value="">
            <input type="hidden" id="exp_remove_image" name="remove_image" value="0">

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Title</label>
                <input type="text" id="exp_title" name="title" required
                    class="w-full px-4 py-2 rounded-xl border border-slate-300 text-sm outline-none focus:ring-2 focus:ring-amber-500">
            </div>
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Subtitle</label>
                <input type="text" id="exp_subtitle" name="subtitle"
                    class="w-full px-4 py-2 rounded-xl border border-slate-300 text-sm outline-none focus:ring-2 focus:ring-amber-500">
            </div>
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Description</label>
                <textarea id="exp_description" name="description" rows="3"
                    class="w-full px-4 py-2 rounded-xl border border-slate-300 text-sm outline-none focus:ring-2 focus:ring-amber-500"></textarea>
            </div>
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Badge (e.g. Most Magical, Bestseller, Signature Craft)</label>
                <input type="text" id="exp_badge" name="badge" placeholder="e.g. Most Magical, Bestseller, Top Rated"
                    class="w-full px-4 py-2 rounded-xl border border-slate-300 text-sm outline-none focus:ring-2 focus:ring-amber-500">
                <input type="hidden" id="exp_price" name="base_price" value="0">
            </div>
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">
                    Experience Images (16:9 Landscape &bull; 1 or Multiple Files)
                </label>
                
                <!-- Multiple Images Preview Grid Area -->
                <div id="exp_images_grid_container" class="mb-3 hidden">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-bold text-slate-700 flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-amber-500 inline-block animate-pulse"></span>
                            <span id="exp_images_count_label">0 Images</span>
                        </span>
                        <span id="exp_autoroll_hint" class="text-[11px] font-semibold text-amber-700 bg-amber-50 px-2 py-0.5 rounded-full border border-amber-200 hidden">
                            ⚡ Auto-rolls every 10 sec on site with Next/Prev
                        </span>
                    </div>
                    
                    <div id="exp_images_preview_grid" class="grid grid-cols-2 sm:grid-cols-3 gap-2.5 max-h-56 overflow-y-auto p-2 bg-slate-50 rounded-xl border border-slate-200">
                        <!-- Thumbnails rendered dynamically -->
                    </div>
                </div>

                <!-- Multi-file Upload Input (Native 16:9 Landscape Pipeline) -->
                <div class="border-2 border-dashed border-slate-300 hover:border-amber-500 rounded-xl p-4 text-center cursor-pointer transition-colors bg-slate-50/50">
                    <input type="file" id="exp_image_files" name="image_files[]" multiple accept="image/jpeg,image/png,image/webp"
                        class="hidden" onchange="handleMultipleFileSelect(this)">
                    <label for="exp_image_files" class="cursor-pointer block">
                        <div class="w-10 h-10 mx-auto rounded-full bg-amber-100 text-amber-600 flex items-center justify-center mb-2">
                            📷
                        </div>
                        <span class="text-sm font-semibold text-slate-700 block">Click to upload 16:9 images (Select 1 or more)</span>
                        <span class="text-xs text-slate-500 block mt-0.5">JPG, PNG, or WEBP (Max 8MB each &bull; 2+ images enable 10-second auto-rolling slideshow)</span>
                    </label>
                </div>

                <!-- Hidden inputs for tracking existing images and backward compatibility -->
                <input type="hidden" id="exp_existing_images_json" name="existing_images_json" value="[]">
                <input type="hidden" id="exp_existing_image" name="existing_image" value="">
                <input type="hidden" id="exp_remove_image" name="remove_image" value="0">

                <!-- Collapsible URL fallback -->
                <details class="mt-2 text-xs text-slate-600">
                    <summary class="cursor-pointer text-slate-500 hover:text-slate-700 font-medium select-none">Or paste direct image URLs (optional)</summary>
                    <div class="mt-2 space-y-1.5">
                        <textarea id="exp_image_urls" name="image_urls" rows="2" placeholder="https://example.com/image1.jpg&#10;https://example.com/image2.jpg"
                            class="w-full px-3 py-1.5 rounded-lg border border-slate-300 text-xs outline-none focus:ring-1 focus:ring-amber-500"
                            onchange="handleDirectUrlsInput(this.value)"></textarea>
                        <p class="text-[10px] text-slate-400">1 URL per line or comma-separated</p>
                    </div>
                </details>
            </div>
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Features List (1 per
                    line)</label>
                <textarea id="exp_features" name="features" rows="4" placeholder="2-Hour Setup&#10;Rose Petal Carpet"
                    class="w-full px-4 py-2 rounded-xl border border-slate-300 text-sm outline-none focus:ring-2 focus:ring-amber-500"></textarea>
            </div>
            <div class="grid grid-cols-2 gap-3 items-center">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Display
                        Order</label>
                    <input type="number" id="exp_order" name="display_order" value="1"
                        class="w-full px-4 py-2 rounded-xl border border-slate-300 text-sm outline-none focus:ring-2 focus:ring-amber-500">
                </div>
                <div class="pt-4">
                    <label class="flex items-center gap-2 cursor-pointer font-semibold text-sm text-slate-700">
                        <input type="checkbox" id="exp_active" name="is_active" value="1" checked
                            class="w-4 h-4 text-amber-600 rounded"> Active on Site
                    </label>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
                <button type="button" onclick="closeExpModal()"
                    class="px-4 py-2 text-slate-600 text-sm font-semibold">Cancel</button>
                <button type="submit"
                    class="px-5 py-2 gold-gradient text-slate-950 font-bold rounded-xl text-sm shadow-md">Save
                    Experience</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: ADD/EDIT UPGRADE -->
<div id="upgModal"
    class="hidden fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="bg-white max-w-md w-full rounded-2xl p-6 shadow-2xl space-y-4">
        <h3 id="upgModalTitle" class="text-lg font-serif font-bold text-slate-900 border-b border-slate-100 pb-3">Add
            Upgrade</h3>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="save_upgrade">
            <input type="hidden" id="upg_id" name="id" value="">

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Upgrade Name</label>
                <input type="text" id="upg_name" name="name" required
                    class="w-full px-4 py-2 rounded-xl border border-slate-300 text-sm outline-none focus:ring-2 focus:ring-amber-500">
            </div>
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Description</label>
                <textarea id="upg_description" name="description" rows="2"
                    class="w-full px-4 py-2 rounded-xl border border-slate-300 text-sm outline-none focus:ring-2 focus:ring-amber-500"></textarea>
            </div>
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Icon Name</label>
                <select id="upg_icon" name="icon"
                    class="w-full px-4 py-2 rounded-xl border border-slate-300 text-sm outline-none focus:ring-2 focus:ring-amber-500 bg-white">
                    <option value="Music">Music (Live Singer)</option>
                    <option value="Camera">Camera (Photographer)</option>
                    <option value="Gift">Gift (Keepsake Box)</option>
                    <option value="Sparkles">Sparkles (Pyro)</option>
                    <option value="Heart">Heart (Balloons)</option>
                    <option value="Star">Star (General)</option>
                </select>
                <input type="hidden" id="upg_price" name="price" value="0">
            </div>
            <div class="grid grid-cols-2 gap-3 items-center">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Display
                        Order</label>
                    <input type="number" id="upg_order" name="display_order" value="1"
                        class="w-full px-4 py-2 rounded-xl border border-slate-300 text-sm outline-none focus:ring-2 focus:ring-amber-500">
                </div>
                <div class="pt-4">
                    <label class="flex items-center gap-2 cursor-pointer font-semibold text-sm text-slate-700">
                        <input type="checkbox" id="upg_active" name="is_active" value="1" checked
                            class="w-4 h-4 text-amber-600 rounded"> Active on Site
                    </label>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
                <button type="button" onclick="closeUpgModal()"
                    class="px-4 py-2 text-slate-600 text-sm font-semibold">Cancel</button>
                <button type="submit"
                    class="px-5 py-2 gold-gradient text-slate-950 font-bold rounded-xl text-sm shadow-md">Save
                    Upgrade</button>
            </div>
        </form>
    </div>
</div>

<script>
    let currentExpImages = [];

    function renderExpImages() {
        const container = document.getElementById('exp_images_grid_container');
        const grid = document.getElementById('exp_images_preview_grid');
        const countLabel = document.getElementById('exp_images_count_label');
        const autoRollHint = document.getElementById('exp_autoroll_hint');
        const existingJsonInput = document.getElementById('exp_existing_images_json');
        const fallbackExistingImg = document.getElementById('exp_existing_image');

        if (!currentExpImages || currentExpImages.length === 0) {
            container.classList.add('hidden');
            existingJsonInput.value = '[]';
            fallbackExistingImg.value = '';
            return;
        }

        container.classList.remove('hidden');
        countLabel.textContent = currentExpImages.length + (currentExpImages.length === 1 ? ' Image' : ' Images');

        if (currentExpImages.length > 1) {
            autoRollHint.classList.remove('hidden');
        } else {
            autoRollHint.classList.add('hidden');
        }

        grid.innerHTML = '';
        currentExpImages.forEach((img, idx) => {
            const card = document.createElement('div');
            card.className = 'relative aspect-video rounded-lg overflow-hidden border border-slate-200 shadow-xs bg-slate-900 group';
            card.innerHTML = `
                <img src="${img.url}" class="w-full h-full object-cover" alt="Slide ${idx + 1}">
                <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center p-1">
                    <button type="button" onclick="removeExpImageAt(${idx})" class="px-2 py-1 bg-rose-600 hover:bg-rose-700 text-white text-[10px] font-bold rounded shadow cursor-pointer">
                        ✕ Remove
                    </button>
                </div>
                <div class="absolute top-1 left-1">
                    <span class="px-1.5 py-0.5 rounded text-[9px] font-bold ${idx === 0 ? 'bg-amber-500 text-black' : 'bg-black/60 text-white'}">
                        ${idx === 0 ? '★ Cover' : '#' + (idx + 1)}
                    </span>
                </div>
            `;
            grid.appendChild(card);
        });

        const existingUrls = currentExpImages
            .filter(item => item.type === 'existing')
            .map(item => item.url);
        existingJsonInput.value = JSON.stringify(existingUrls);
        fallbackExistingImg.value = existingUrls[0] || '';
    }

    function handleMultipleFileSelect(input) {
        if (!input.files || input.files.length === 0) return;
        const allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];

        Array.from(input.files).forEach(file => {
            if (!allowed.includes(file.type)) {
                alert('File ' + file.name + ' is not a valid JPG, PNG, or WEBP image.');
                return;
            }
            if (file.size > 8 * 1024 * 1024) {
                alert('File ' + file.name + ' exceeds 8MB limit.');
                return;
            }
            const previewUrl = URL.createObjectURL(file);
            currentExpImages.push({
                type: 'new_file',
                fileObj: file,
                url: previewUrl,
                name: file.name
            });
        });

        renderExpImages();
        syncDataTransfer();
    }

    function removeExpImageAt(index) {
        if (index >= 0 && index < currentExpImages.length) {
            currentExpImages.splice(index, 1);
            renderExpImages();
            syncDataTransfer();
        }
    }

    function syncDataTransfer() {
        try {
            const dt = new DataTransfer();
            currentExpImages.forEach(item => {
                if (item.fileObj) {
                    dt.items.add(item.fileObj);
                }
            });
            const fileInput = document.getElementById('exp_image_files');
            if (fileInput) fileInput.files = dt.files;
        } catch (e) {}
    }

    function handleDirectUrlsInput(rawText) {
        if (!rawText) return;
        const lines = rawText.split(/[\r\n,]+/);
        lines.forEach(line => {
            const trimmed = line.trim();
            if (trimmed.startsWith('http')) {
                currentExpImages.push({
                    type: 'existing',
                    url: trimmed,
                    name: trimmed.split('/').pop()
                });
            }
        });
        document.getElementById('exp_image_urls').value = '';
        renderExpImages();
    }

    function openExpModal() {
        document.getElementById('expModalTitle').innerText = 'Add New Experience';
        document.getElementById('exp_id').value = '';
        document.getElementById('exp_title').value = '';
        document.getElementById('exp_subtitle').value = '';
        document.getElementById('exp_description').value = '';
        if (document.getElementById('exp_price')) document.getElementById('exp_price').value = '0';
        document.getElementById('exp_badge').value = '';
        document.getElementById('exp_existing_image').value = '';
        document.getElementById('exp_existing_images_json').value = '[]';
        document.getElementById('exp_remove_image').value = '0';
        document.getElementById('exp_image_files').value = '';
        document.getElementById('exp_image_urls').value = '';
        currentExpImages = [];
        renderExpImages();
        document.getElementById('exp_features').value = '';
        document.getElementById('exp_order').value = '1';
        document.getElementById('exp_active').checked = true;
        document.getElementById('expModal').classList.remove('hidden');
    }

    function editExp(exp) {
        document.getElementById('expModalTitle').innerText = 'Edit Experience';
        document.getElementById('exp_id').value = exp.id;
        document.getElementById('exp_title').value = exp.title || '';
        document.getElementById('exp_subtitle').value = exp.subtitle || '';
        document.getElementById('exp_description').value = exp.description || '';
        if (document.getElementById('exp_price')) document.getElementById('exp_price').value = '0';
        document.getElementById('exp_badge').value = exp.badge || '';
        
        // Multi-image setup
        currentExpImages = [];
        let imgList = [];
        if (exp.images) {
            try {
                imgList = typeof exp.images === 'string' ? JSON.parse(exp.images) : exp.images;
            } catch (e) {
                imgList = [];
            }
        }
        if ((!Array.isArray(imgList) || imgList.length === 0) && exp.image) {
            imgList = [exp.image];
        }
        if (Array.isArray(imgList)) {
            imgList.forEach(u => {
                if (u) currentExpImages.push({ type: 'existing', url: u, name: u.split('/').pop() });
            });
        }
        renderExpImages();

        document.getElementById('exp_image_files').value = '';
        document.getElementById('exp_image_urls').value = '';
        document.getElementById('exp_remove_image').value = '0';

        let feats = [];
        try { feats = JSON.parse(exp.features); } catch (e) { feats = [exp.features]; }
        document.getElementById('exp_features').value = Array.isArray(feats) ? feats.join('\n') : '';

        document.getElementById('exp_order').value = exp.display_order || 1;
        document.getElementById('exp_active').checked = exp.is_active == 1;
        document.getElementById('expModal').classList.remove('hidden');
    }

    function closeExpModal() {
        document.getElementById('expModal').classList.add('hidden');
    }

    function openUpgModal() {
        document.getElementById('upgModalTitle').innerText = 'Add New Upgrade';
        document.getElementById('upg_id').value = '';
        document.getElementById('upg_name').value = '';
        document.getElementById('upg_description').value = '';
        if (document.getElementById('upg_price')) document.getElementById('upg_price').value = '0';
        document.getElementById('upg_icon').value = 'Sparkles';
        document.getElementById('upg_order').value = '1';
        document.getElementById('upg_active').checked = true;
        document.getElementById('upgModal').classList.remove('hidden');
    }

    function editUpg(upg) {
        document.getElementById('upgModalTitle').innerText = 'Edit Upgrade';
        document.getElementById('upg_id').value = upg.id;
        document.getElementById('upg_name').value = upg.name || '';
        document.getElementById('upg_description').value = upg.description || '';
        if (document.getElementById('upg_price')) document.getElementById('upg_price').value = '0';
        document.getElementById('upg_icon').value = upg.icon || 'Sparkles';
        document.getElementById('upg_order').value = upg.display_order || 1;
        document.getElementById('upg_active').checked = upg.is_active == 1;
        document.getElementById('upgModal').classList.remove('hidden');
    }

    function closeUpgModal() {
        document.getElementById('upgModal').classList.add('hidden');
    }
</script>

<?php require_once 'admin_footer.php'; ?>