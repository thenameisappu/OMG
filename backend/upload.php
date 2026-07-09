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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $file = $_FILES['file'];
    $fileName = basename($file['name']);

    // Validate file type properly
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

    $targetFile = $uploadDir . uniqid() . '_' . $fileName;

    if (move_uploaded_file($file['tmp_name'], $targetFile)) {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'];

        // Determine base path dynamically
        // If the script is at /backend/upload.php, we want the URL to be /backend/uploads/...
        // dirname($_SERVER['SCRIPT_NAME']) should return /backend
        $baseDir = dirname($_SERVER['SCRIPT_NAME']);
        $url = $protocol . "://" . $host . $baseDir . "/" . $targetFile;

        $response['success'] = true;
        $response['message'] = 'File uploaded successfully.';
        $response['url'] = $url;
    }
    else {
        $response['message'] = 'Sorry, there was an error uploading your file.';
    }
}
else {
    $response['message'] = 'No file uploaded or invalid request.';
}

echo json_encode($response);
?>
