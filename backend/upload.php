<?php
/**
 * upload.php — Single-file image upload endpoint
 *
 * Dual-storage strategy:
 *   1. Save to PERMANENT store  → domains/omgproductsimages/   (via cropToSquare1000 / move_uploaded_file)
 *   2. Copy  to WEB-ACCESSIBLE  → public_html/backend/uploads/ (via copy())
 *   3. Return URL pointing at   → /backend/uploads/<filename>
 *   4. Save only the FILENAME in MySQL (not the full URL).
 *
 * Compatible with PHP 8.x.
 */

require_once 'config.php';     // defines OMG_PRIMARY_DIR, OMG_SECONDARY_DIR, OMG_IMG_URL_PATH

header("Content-Type: application/json; charset=UTF-8");

// ── Authentication ────────────────────────────────────────────────────────────
if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin_logged_in'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// ── Directory readiness ───────────────────────────────────────────────────────
// Both dirs are created by config.php; we just verify writability here.
foreach ([
    'Permanent storage (domains/omgproductsimages)' => OMG_PRIMARY_DIR,
    'Web-accessible cache (backend/uploads)'        => OMG_SECONDARY_DIR,
] as $label => $dir) {
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => "$label directory could not be created."]);
        exit();
    }
    if (!is_writable($dir)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => "$label directory is not writable."]);
        exit();
    }
}

// ── Allowed types ─────────────────────────────────────────────────────────────
$ALLOWED_MIMES = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
$ALLOWED_EXTS  = ['jpg', 'jpeg', 'png', 'webp'];
$MAX_BYTES     = 5 * 1024 * 1024;   // 5 MB

// ── PHP upload error messages ─────────────────────────────────────────────────
$PHP_UPLOAD_ERRORS = [
    UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload_max_filesize limit.',
    UPLOAD_ERR_FORM_SIZE  => 'File exceeds form MAX_FILE_SIZE limit.',
    UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
    UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
    UPLOAD_ERR_NO_TMP_DIR => 'Missing server temporary folder.',
    UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
    UPLOAD_ERR_EXTENSION  => 'A PHP extension blocked the upload.',
];

/**
 * cropToSquare1000()
 *
 * Crops and resizes the uploaded image to a 1000×1000 px square (center-crop).
 * Output is always JPEG at 90 % quality.
 * Falls back to move_uploaded_file() when the GD extension is unavailable.
 *
 * @param  string $tmpName  PHP temporary file path
 * @param  string $destPath Absolute destination path
 * @return bool
 */
function cropToSquare1000(string $tmpName, string $destPath): bool
{
    // GD unavailable — secure move without processing
    if (!extension_loaded('gd')) {
        return move_uploaded_file($tmpName, $destPath);
    }

    $info = getimagesize($tmpName);
    if (!$info) return false;

    [$srcW, $srcH, $imgType] = [$info[0], $info[1], $info[2]];

    $src = match ($imgType) {
        IMAGETYPE_JPEG => imagecreatefromjpeg($tmpName),
        IMAGETYPE_PNG  => imagecreatefrompng($tmpName),
        IMAGETYPE_WEBP => imagecreatefromwebp($tmpName),
        default        => false,
    };
    if (!$src) return false;

    // Center-crop to largest possible square
    $squareSize = min($srcW, $srcH);
    $cropX = (int)(($srcW - $squareSize) / 2);
    $cropY = (int)(($srcH - $squareSize) / 2);

    $dst = imagecreatetruecolor(1000, 1000);

    // Preserve transparency for PNG / WEBP
    if ($imgType === IMAGETYPE_PNG || $imgType === IMAGETYPE_WEBP) {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
    }

    imagecopyresampled($dst, $src, 0, 0, $cropX, $cropY, 1000, 1000, $squareSize, $squareSize);
    $result = imagejpeg($dst, $destPath, 90);

    imagedestroy($src);
    imagedestroy($dst);

    return $result;
}

// ── Request handling ──────────────────────────────────────────────────────────
$response = ['success' => false, 'message' => '', 'url' => '', 'filename' => ''];

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['file'])) {
    $response['message'] = 'No file uploaded or invalid request method.';
    echo json_encode($response);
    exit();
}

$file = $_FILES['file'];

// 1 ── PHP upload error ───────────────────────────────────────────────────────
if ($file['error'] !== UPLOAD_ERR_OK) {
    $response['message'] = $PHP_UPLOAD_ERRORS[$file['error']] ?? 'Unknown upload error.';
    echo json_encode($response);
    exit();
}

// 2 ── Size validation (max 5 MB) ─────────────────────────────────────────────
if ($file['size'] > $MAX_BYTES) {
    $response['message'] = 'File is too large. Maximum allowed size is 5 MB.';
    echo json_encode($response);
    exit();
}

// 3 ── MIME-type validation ────────────────────────────────────────────────────
$mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
if (!in_array($mimeType, $ALLOWED_MIMES, true)) {
    $response['message'] = 'Invalid file type. Only JPG, JPEG, PNG, and WEBP are allowed.';
    echo json_encode($response);
    exit();
}

// 4 ── Extension validation ────────────────────────────────────────────────────
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, $ALLOWED_EXTS, true)) {
    $response['message'] = 'Invalid file extension. Only .jpg, .jpeg, .png, and .webp are permitted.';
    echo json_encode($response);
    exit();
}

// 5 ── Generate unique filename ────────────────────────────────────────────────
// cropToSquare1000 always writes JPEG → always store with .jpg extension
$uniqueName      = uniqid('img_', true) . '.jpg';
$primaryTarget   = OMG_PRIMARY_DIR   . $uniqueName;   // permanent
$secondaryTarget = OMG_SECONDARY_DIR . $uniqueName;   // web cache

// 6 ── Save to PERMANENT storage via move_uploaded_file (inside cropToSquare1000) ──
if (!cropToSquare1000($file['tmp_name'], $primaryTarget)) {
    error_log('[OMG Upload] cropToSquare1000 failed for: ' . $file['name']);
    $response['message'] = 'Failed to process the uploaded image. Ensure it is a valid image file.';
    echo json_encode($response);
    exit();
}

// 7 ── Copy processed file to WEB-ACCESSIBLE cache (backend/uploads/) ──────────
if (!copy($primaryTarget, $secondaryTarget)) {
    // Non-fatal: permanent copy is safe; sync will restore it after next deploy
    error_log('[OMG Upload] copy() to backend/uploads/ failed for: ' . $uniqueName);
}

// 8 ── Build URL (always served from backend/uploads/) ────────────────────────
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'];
$imageUrl = $protocol . '://' . $host . OMG_IMG_URL_PATH . $uniqueName;

// Return URL + filename (store ONLY the filename in MySQL)
$response['success']  = true;
$response['message']  = 'Image uploaded and processed successfully (1000×1000 px).';
$response['url']      = $imageUrl;
$response['filename'] = $uniqueName;     // ← save this in MySQL

echo json_encode($response);
?>
