<?php
require_once 'config.php';

header("Content-Type: application/json; charset=UTF-8");

// Check Authentication (Allow both user sessions and admin sessions)
if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin_logged_in'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// ── Upload directory (Hostinger domains root, 3 levels above backend/) ────────
// Disk path : /home/u981836125/domains/omgproductsimages/
// Web URL   : configured via IMAGES_BASE_URL in .env
//
// Path breakdown from __DIR__ (= …/public_html/backend):
//   dirname(__DIR__)               → …/public_html
//   dirname(dirname(__DIR__))      → …/ghostwhite-kudu-967584.hostingersite.com
//   dirname(dirname(dirname(__DIR__))) → …/domains
$uploadDir = dirname(dirname(dirname(__DIR__))) . '/omgproductsimages/';

// Public base URL for serving images (set IMAGES_BASE_URL in .env)
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
        exit();
    }
}

if (!is_writable($uploadDir)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Upload directory is not writable. Check server permissions.']);
    exit();
}

$response = ['success' => false, 'message' => '', 'url' => ''];

/**
 * Crop & resize any image to a perfect 1000×1000 px (1:1) square.
 * Center-crop strategy: picks the largest centred square, then scales to 1000×1000.
 * Output is always JPEG at 90 % quality.
 * Falls back to move_uploaded_file() when GD is unavailable.
 */
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $file = $_FILES['file'];

    // ── 1. PHP upload error check ────────────────────────────────────────
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $phpUploadErrors = [
            UPLOAD_ERR_INI_SIZE   => 'File exceeds the server upload_max_filesize limit.',
            UPLOAD_ERR_FORM_SIZE  => 'File exceeds the form MAX_FILE_SIZE limit.',
            UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
            UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing server temporary folder.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION  => 'A PHP extension blocked the upload.',
        ];
        $response['message'] = $phpUploadErrors[$file['error']] ?? 'Unknown upload error.';
        echo json_encode($response);
        exit();
    }

    // ── 2. Size limit (5 MB) ─────────────────────────────────────────────
    if ($file['size'] > 5 * 1024 * 1024) {
        $response['message'] = 'File is too large. Maximum allowed size is 5 MB.';
        echo json_encode($response);
        exit();
    }

    // ── 3. MIME-type validation ──────────────────────────────────────────
    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    $allowedMimeTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];

    if (!in_array($mimeType, $allowedMimeTypes, true)) {
        $response['message'] = 'Invalid file type. Only JPG, JPEG, PNG, and WEBP files are allowed.';
        echo json_encode($response);
        exit();
    }

    // ── 4. Extension validation ──────────────────────────────────────────
    $originalExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];

    if (!in_array($originalExt, $allowedExts, true)) {
        $response['message'] = 'Invalid file extension. Only .jpg, .jpeg, .png, and .webp are permitted.';
        echo json_encode($response);
        exit();
    }

    // ── 5. Generate unique filename & save ───────────────────────────────
    // cropToSquare1000 always outputs JPEG, so extension is .jpg
    $uniqueName = uniqid('img_', true) . '.jpg';
    $targetFile = $uploadDir . $uniqueName;

    // ── 6. Crop & resize to 1000×1000, then save ────────────────────────
    if (cropToSquare1000($file['tmp_name'], $targetFile)) {
        // Full public URL — served via the domain/subdomain configured in IMAGES_BASE_URL
        $url = $imagesBaseUrl . '/omgproductsimages/' . $uniqueName;

        $response['success']  = true;
        $response['message']  = 'File uploaded and cropped to 1000×1000 px (1:1) successfully.';
        $response['url']      = $url;
        $response['filename'] = $uniqueName;
        $response['path']     = 'omgproductsimages/' . $uniqueName;
    } else {
        $response['message'] = 'Failed to process the uploaded image. Ensure the file is a valid image.';
    }
} else {
    $response['message'] = 'No file uploaded or invalid request method.';
}

echo json_encode($response);
?>
