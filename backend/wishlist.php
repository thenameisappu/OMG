<?php
require_once 'config.php';

header("Content-Type: application/json; charset=UTF-8");

$database = new Database();
$db = $database->getConnection();

if ($db === null) {
    http_response_code(500);
    echo json_encode(["message" => "Database connection failed."]);
    exit();
}

$userId = authenticate();
$action = isset($_GET['action']) ? $_GET['action'] : '';
$data = json_decode(file_get_contents("php://input"));

switch ($action) {
    case 'get_wishlist':
        getWishlist($db, $userId);
        break;
    case 'add':
        addToWishlist($db, $userId, $data);
        break;
    case 'remove':
        removeFromWishlist($db, $userId);
        break;
    case 'check':
        checkWishlist($db, $userId);
        break;
    default:
        echo json_encode(["message" => "Invalid action"]);
        break;
}

function getWishlist($db, $userId)
{
    $query = "SELECT w.id as wishlist_id, w.product_id, p.name, p.price, p.image, p.slug 
              FROM wishlist w 
              JOIN products p ON w.product_id = p.id 
              WHERE w.user_id = :user_id 
              ORDER BY w.created_at DESC";

    $stmt = $db->prepare($query);
    $stmt->bindParam(":user_id", $userId);
    $stmt->execute();

    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $formatted = [];

    foreach ($results as $row) {
        $formatted[] = [
            'id' => $row['wishlist_id'],
            'product_id' => $row['product_id'],
            'products' => [
                'name' => $row['name'],
                'price' => $row['price'],
                'image_url' => $row['image'],
                'slug' => $row['slug']
            ]
        ];
    }

    echo json_encode($formatted);
}

function addToWishlist($db, $userId, $data)
{
    if (!isset($data->product_id)) {
        http_response_code(400);
        echo json_encode(["message" => "Product ID required"]);
        return;
    }

    $checkQuery = "SELECT id FROM wishlist WHERE user_id = :user_id AND product_id = :product_id";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->bindParam(":user_id", $userId);
    $checkStmt->bindParam(":product_id", $data->product_id);
    $checkStmt->execute();

    if ($checkStmt->rowCount() > 0) {
        echo json_encode(["message" => "Product already in wishlist"]);
        return;
    }

    $query = "INSERT INTO wishlist (user_id, product_id) VALUES (:user_id, :product_id)";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":user_id", $userId);
    $stmt->bindParam(":product_id", $data->product_id);

    if ($stmt->execute()) {
        echo json_encode(["message" => "Added to wishlist"]);
    }
    else {
        http_response_code(503);
        echo json_encode(["message" => "Unable to add to wishlist"]);
    }
}

function removeFromWishlist($db, $userId)
{
    $productId = isset($_GET['product_id']) ? $_GET['product_id'] : '';
    if (empty($productId)) {
        http_response_code(400);
        echo json_encode(["message" => "Product ID required"]);
        return;
    }

    $query = "DELETE FROM wishlist WHERE user_id = :user_id AND product_id = :product_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":user_id", $userId);
    $stmt->bindParam(":product_id", $productId);

    if ($stmt->execute()) {
        echo json_encode(["message" => "Removed from wishlist"]);
    }
    else {
        http_response_code(503);
        echo json_encode(["message" => "Unable to remove from wishlist"]);
    }
}

function checkWishlist($db, $userId)
{
    $productId = isset($_GET['product_id']) ? $_GET['product_id'] : '';
    if (empty($productId)) {
        echo json_encode(false);
        return;
    }

    $query = "SELECT id FROM wishlist WHERE user_id = :user_id AND product_id = :product_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":user_id", $userId);
    $stmt->bindParam(":product_id", $productId);
    $stmt->execute();

    echo json_encode($stmt->rowCount() > 0);
}
?>