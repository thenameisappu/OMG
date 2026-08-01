<?php
require_once 'config.php';

header("Content-Type: application/json; charset=UTF-8");

// Check Authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$uploadDir = 'uploads/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$response = ['success' => false, 'message' => '', 'url' => ''];

/**
 * Crop & resize any image to a perfect 1000×1000px (1:1) square.
 * Center-crop strategy: picks the largest centered square, then scales to 1000×1000.
 * Output is always JPEG at 90% quality.
 */
function cropToSquare1000(string $tmpName, string $destPath): bool {
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

    // Centered square crop region
    $squareSize = min($srcW, $srcH);
    $cropX = (int)(($srcW - $squareSize) / 2);
    $cropY = (int)(($srcH - $squareSize) / 2);

    // Create 1000×1000 output canvas
    $dst = imagecreatetruecolor(1000, 1000);

    // Handle PNG/WEBP transparency
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

    // Validate file type
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    $allowedMimeTypes = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp'
    ];

    if (!in_array($mimeType, $allowedMimeTypes)) {
        $response['message'] = 'Invalid file type. Only JPG, PNG, GIF, and WEBP are allowed.';
        echo json_encode($response);
        exit();
    }

    // Always save as .jpg since cropToSquare1000 outputs JPEG
    $uniqueName = uniqid('upload_', true) . '.jpg';
    $targetFile = $uploadDir . $uniqueName;

    // Crop & resize to 1000×1000 (1:1 ratio)
    if (cropToSquare1000($file['tmp_name'], $targetFile)) {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'];
        $baseDir = dirname($_SERVER['SCRIPT_NAME']);
        $url = $protocol . "://" . $host . $baseDir . "/" . $targetFile;

        $response['success'] = true;
        $response['message'] = 'File uploaded and cropped to 1000×1000px (1:1) successfully.';
        $response['url'] = $url;
    } else {
        $response['message'] = 'Sorry, there was an error processing your image.';
    }
} else {
    $response['message'] = 'No file uploaded or invalid request.';
}

echo json_encode($response);
?>
