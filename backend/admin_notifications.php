<?php
require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Basic auth check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$database = new Database();
$db = $database->getConnection();

try {
    // Get counts of orders, inquiries, and customisations
    $order_count = $db->query("SELECT COUNT(*) FROM orders")->fetchColumn();
    $inquiry_count = $db->query("SELECT COUNT(*) FROM inquiries")->fetchColumn();
    $customisation_count = $db->query("SELECT COUNT(*) FROM customisations")->fetchColumn();

    echo json_encode([
        'orders' => (int)$order_count,
        'inquiries' => (int)$inquiry_count,
        'customisations' => (int)$customisation_count
    ]);
}
catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
