<?php
/**
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

/**
 * handleSubscriptionImageUpload()
 *
 * Reuses the existing Product image pipeline for Subscription plan images.
 * Uses the same validation, cropToSquare1000, filename strategy, and
 * dual-storage (OMG_PRIMARY_DIR + OMG_SECONDARY_DIR / uploads cache).
 */
function handleSubscriptionImageUpload(string $fileKey, string $planName = '', string $existingUrl = ''): string
{
    if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
        return $existingUrl;
    }

    $file    = $_FILES[$fileKey];
    $size    = $file['size'];
    $tmpName = $file['tmp_name'];
    $origName = $file['name'];

    // 5 MB limit (same as Products)
    if ($size > 5 * 1024 * 1024) {
        throw new Exception('File is too large. Maximum allowed size is 5 MB.');
    }

    // MIME validation (same as Products)
    $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($tmpName);
    $allowedMimes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
    if (!in_array($mimeType, $allowedMimes, true)) {
        throw new Exception('Invalid file type. Only JPG, JPEG, PNG, and WEBP are allowed.');
    }

    // Extension validation (same as Products)
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
    if (!in_array($ext, $allowedExts, true)) {
        throw new Exception("Invalid file extension ('.$ext'). Only .jpg, .jpeg, .png, and .webp are permitted.");
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

    // Slug-based filename (same as Products)
    $targetFilename = generateProductImageFilename(
        !empty($planName) ? $planName : 'subscription',
        'main',
        'jpg'
    );

    $primaryTarget   = $primaryDir   . $targetFilename;
    $secondaryTarget = $secondaryDir . $targetFilename;

    // Crop and save (same as Products)
    if (!cropToSquare1000($tmpName, $primaryTarget)) {
        throw new Exception('Failed to process and save the uploaded image.');
    }

    // Cache copy (same as Products)
    if (is_writable($secondaryDir) && !copy($primaryTarget, $secondaryTarget)) {
        error_log('[OMG Upload] copy() to backend/uploads/ failed for subscription image: ' . $targetFilename);
    }

    // Delete old image (only if it was a locally stored image, not a CDN URL)
    if (!empty($existingUrl) && strpos($existingUrl, OMG_IMG_URL_PATH) !== false) {
        $oldFilename = basename($existingUrl);
        if ($oldFilename !== $targetFilename) {
            deleteLocalImage($existingUrl);
        }
    }

    // Build URL (same pattern as Products using OMG_IMG_URL_PATH)
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    return $protocol . '://' . $_SERVER['HTTP_HOST'] . OMG_IMG_URL_PATH . $targetFilename;
}

$message = '';
$error   = '';

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
            </div>
        </form>
    </div>
</div>

<script>
    function openPlanImageModal(plan) {
        document.getElementById('planModalTitle').innerText = 'Edit Image: ' + plan.name;
        document.getElementById('plan_id_input').value = plan.id;
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
        }

        document.getElementById('planImageModal').classList.remove('hidden');
    }

    function closePlanImageModal() {
        document.getElementById('planImageModal').classList.add('hidden');
        document.getElementById('plan_image_input').value = '';
        document.getElementById('remove_image_flag').value = '0';
    }

    function previewPlanImage(input) {
        if (!input.files || !input.files[0]) return;
        const file = input.files[0];

        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
        if (!allowedTypes.includes(file.type)) {
            alert('Invalid file type. Only JPG, PNG, and WEBP are allowed.');
            input.value = '';
            return;
        }
        if (file.size > 5 * 1024 * 1024) {
            alert('File is too large. Maximum size is 5 MB.');
            input.value = '';
            return;
        }

        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('imagePreview').src = e.target.result;
            document.getElementById('imagePreviewWrapper').classList.remove('hidden');
            document.getElementById('noImagePlaceholder').classList.add('hidden');
            document.getElementById('previewLabel').textContent = 'New image (not yet saved)';
            document.getElementById('remove_image_flag').value = '0';
        };
        reader.readAsDataURL(file);
    }

    function removeImage() {
        if (!confirm('Remove this image? It will be deleted when you save.')) return;
        document.getElementById('imagePreview').src = '';
        document.getElementById('imagePreviewWrapper').classList.add('hidden');
        document.getElementById('noImagePlaceholder').classList.remove('hidden');
        document.getElementById('plan_image_input').value = '';
        document.getElementById('remove_image_flag').value = '1';
    }

    document.getElementById('planImageModal').addEventListener('click', function(e) {
        if (e.target === this) closePlanImageModal();
    });
</script>

<?php require_once 'admin_footer.php'; ?>
