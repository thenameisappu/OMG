<?php
// Enable error reporting for debugging
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// FEATURE 4: USE SYSTEM TIME FOR ORDERS
date_default_timezone_set('Asia/Kolkata');

$logFile = __DIR__ . '/debug_orders.log';
function logDebug($message)
{
    global $logFile;
    $entry = date('Y-m-d H:i:s') . " - " . $message . "\n";
    file_put_contents($logFile, $entry, FILE_APPEND);
}

logDebug("Request received: " . $_SERVER['REQUEST_URI']);
logDebug("Mehtod: " . $_SERVER['REQUEST_METHOD']);

// Log ALL headers to debug Authorization stripping
$headers = apache_request_headers();
logDebug("Headers: " . print_r($headers, true));

require_once 'config.php';

$database = new Database();
$db = $database->getConnection();

if ($db === null) {
    $errorMsg = "Database connection failed.";
    logDebug("Error: " . $errorMsg);
    http_response_code(500);
    echo json_encode(["message" => $errorMsg]);
    exit();
}

// Check for simple connectivity test
if (isset($_GET['action']) && $_GET['action'] === 'test') {
    logDebug("Test action received.");
    echo json_encode(["status" => "success", "message" => "Backend is reachable", "log_file" => $logFile]);
    exit();
}

$data = json_decode(file_get_contents("php://input"));
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Start session to access admin variables
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Authenticate is now handled per-action
$userId = null;


switch ($action) {
    case 'create_order':
        $userId = authenticate();
        createOrder($db, $userId, $data);
        break;
    case 'get_orders':
        $userId = authenticate();
        getUserOrders($db, $userId);
        break;
    case 'get_order':
        $userId = authenticate();
        $orderId = isset($_GET['id']) ? $_GET['id'] : '';
        getOrderById($db, $userId, $orderId);
        break;
    case 'cancel_order':
        $userId = authenticate();
        $orderId = isset($_GET['id']) ? $_GET['id'] : '';
        cancelOrder($db, $userId, $orderId);
        break;
    case 'update_status':
        // FEATURE 1: UPDATE ORDER STATUS (ADMIN ONLY)
        if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
            http_response_code(401);
            echo json_encode(["message" => "Unauthorized - Please login as Admin"]);
            exit();
        }
        updateOrderStatus($db, $data);
        break;
    default:
        echo json_encode(["message" => "Invalid action"]);
        break;
}

function createOrder($db, $userId, $data)
{
    try {
        // FEATURE 6: DATA INTEGRITY (Transaction)
        $db->beginTransaction();

        // FEATURE 3: STOCK CONTROL LOGIC
        // Validate stock for ALL items before creating order
        foreach ($data->items as $item) {
            $stockQuery = "SELECT stock_status, stock_quantity, name, is_active FROM products WHERE id = :id";
            $stockStmt = $db->prepare($stockQuery);
            $stockStmt->bindParam(":id", $item->product_id);
            $stockStmt->execute();

            if ($stockStmt->rowCount() > 0) {
                $product = $stockStmt->fetch(PDO::FETCH_ASSOC);
                if ((int)$product['is_active'] !== 1) {
                    throw new Exception("Product '" . $product['name'] . "' is no longer active.");
                }
                if ($product['stock_status'] === 'out_of_stock' || (int)$product['stock_quantity'] < $item->quantity) {
                    throw new Exception("Product '" . $product['name'] . "' has insufficient stock available (Requested: " . $item->quantity . ", Available: " . $product['stock_quantity'] . ").");
                }
            }
            else {
                throw new Exception("Product ID " . $item->product_id . " not found.");
            }
        }

        // Create Order
        $uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );

        // FEATURE 5: FORCE CITY TO BANGALORE
        // Appending 'Bangalore' to address to ensure it is captured
        $finalAddress = $data->delivery_address;
        if (stripos($finalAddress, 'Bangalore') === false) {
            $finalAddress .= ", Bangalore";
        }

        $query = "INSERT INTO orders (id, user_id, total_amount, customer_name, customer_email, customer_phone, delivery_address, delivery_option, delivery_date, delivery_time, payment_method, status) VALUES (:id, :user_id, :total_amount, :customer_name, :customer_email, :customer_phone, :delivery_address, :delivery_option, :delivery_date, :delivery_time, :payment_method, 'pending')";

        $stmt = $db->prepare($query);
        $stmt->bindParam(":id", $uuid);
        $stmt->bindParam(":user_id", $userId);
        $stmt->bindParam(":total_amount", $data->total_amount);
        $stmt->bindParam(":customer_name", $data->customer_name);
        $stmt->bindParam(":customer_email", $data->customer_email);
        $stmt->bindParam(":customer_phone", $data->customer_phone);
        $stmt->bindParam(":delivery_address", $finalAddress);
        $stmt->bindParam(":delivery_option", $data->delivery_option);
        $rawDate = isset($data->delivery_date) ? $data->delivery_date : null;
        $stmt->bindParam(":delivery_date", $rawDate);
        $stmt->bindParam(":delivery_time", $data->delivery_time);
        $stmt->bindParam(":payment_method", $data->payment_method);

        $stmt->execute();

        // Create Order Items & Deduct Stock
        foreach ($data->items as $item) {
            $itemQuery = "INSERT INTO order_items (order_id, product_id, quantity, unit_price) VALUES (:order_id, :product_id, :quantity, :unit_price)";
            $itemStmt = $db->prepare($itemQuery);
            $itemStmt->bindParam(":order_id", $uuid);
            $itemStmt->bindParam(":product_id", $item->product_id);
            $itemStmt->bindParam(":quantity", $item->quantity);
            $itemStmt->bindParam(":unit_price", $item->unit_price);
            $itemStmt->execute();

            // Deduct stock quantity and update stock status accordingly
            $deductQuery = "UPDATE products 
                            SET stock_quantity = GREATEST(0, stock_quantity - :qty),
                                stock_status = IF(stock_quantity - :qty <= 0, 'out_of_stock', 'in_stock')
                            WHERE id = :id";
            $deductStmt = $db->prepare($deductQuery);
            $deductStmt->bindParam(":qty", $item->quantity);
            $deductStmt->bindParam(":id", $item->product_id);
            $deductStmt->execute();
        }

        // Generate WhatsApp Notification Message
        $admin_whatsapp = "917353363881"; // Admin number
        $apikey = "REPLACE_WITH_YOUR_API_KEY"; // TODO: User needs to set this

        $msg_userId = $userId;
        $msg_name = $data->customer_name;
        $msg_email = $data->customer_email;
        $msg_phone = $data->customer_phone;
        $msg_address = $finalAddress;
        $msg_option = $data->delivery_option;
        $msg_date = isset($data->delivery_date) ? $data->delivery_date : 'Not specified';
        $msg_time = $data->delivery_time;
        $msg_payment = $data->payment_method;
        $msg_total = $data->total_amount;
        $msg_created = date('Y-m-d H:i:s');

        $message = "🛒 *New Order Received*\n\n" .
            "📋 *Order Details:*\n" .
            "User ID: $msg_userId\n" .
            "Customer Name: $msg_name\n" .
            "Email: $msg_email\n" .
            "Phone: $msg_phone\n\n" .
            "📍 *Delivery Address:*\n" .
            "$msg_address\n\n" .
            "🚚 *Delivery Info:*\n" .
            "Option: $msg_option\n" .
            "Date: $msg_date\n" .
            "Time: $msg_time\n\n" .
            "💳 *Payment Method:* $msg_payment\n" .
            "💰 *Total Amount:* ₹$msg_total\n\n" .
            "🕒 Order Time: $msg_created";

        $encoded_message = urlencode($message);

        // Send to CallMeBot
        $url = "https://api.callmebot.com/whatsapp.php?phone=$admin_whatsapp&text=$encoded_message&apikey=$apikey";

        // Use file_get_contents to send the request (server-side)
        // Suppress errors to avoid breaking the order flow if API fails
        $result = @file_get_contents($url);

        $db->commit();
        echo json_encode([
            "id" => $uuid,
            "status" => "pending",
            "whatsapp_sent" => ($result !== false)
        ]);
    }
    catch (Exception $e) {
        $db->rollBack();
        http_response_code(400); // 400 for validation logic/stock issues
        echo json_encode(["message" => "Failed to create order: " . $e->getMessage()]);
    }
}

function getUserOrders($db, $userId)
{
    $query = "SELECT * FROM orders WHERE user_id = :user_id ORDER BY created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":user_id", $userId);
    $stmt->execute();

    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch items for each order
    foreach ($orders as &$order) {
        $itemQuery = "SELECT oi.*, p.name, p.image FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = :order_id";
        $itemStmt = $db->prepare($itemQuery);
        $itemStmt->bindParam(":order_id", $order['id']);
        $itemStmt->execute();
        $order['order_items'] = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
        // Rename for frontend compatibility if needed, or adjust frontend
        foreach ($order['order_items'] as &$item) {
            $item['products'] = ["name" => $item['name'], "image" => $item['image']];
        }
    }

    echo json_encode($orders);
}

function getOrderById($db, $userId, $orderId)
{
    $query = "SELECT * FROM orders WHERE id = :id AND user_id = :user_id LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":id", $orderId);
    $stmt->bindParam(":user_id", $userId);
    $stmt->execute();

    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($order) {
        $itemQuery = "SELECT oi.*, p.name, p.image FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = :order_id";
        $itemStmt = $db->prepare($itemQuery);
        $itemStmt->bindParam(":order_id", $orderId);
        $itemStmt->execute();
        $order['order_items'] = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($order['order_items'] as &$item) {
            $item['products'] = ["name" => $item['name'], "image" => $item['image']];
        }
        echo json_encode($order);
    }
    else {
        echo json_encode(null);
    }
}

function cancelOrder($db, $userId, $orderId)
{
    if (empty($orderId)) {
        http_response_code(400);
        echo json_encode(["message" => "Order ID is required."]);
        return;
    }

    try {
        // Check if order exists, belongs to user, and is in a cancellable status
        $query = "SELECT status FROM orders WHERE id = :id AND user_id = :user_id LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":id", $orderId);
        $stmt->bindParam(":user_id", $userId);
        $stmt->execute();

        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            http_response_code(404);
            echo json_encode(["message" => "Order not found or unauthorized."]);
            return;
        }

        $cancellableStatuses = ['pending', 'order accepted'];
        if (!in_array(strtolower($order['status']), $cancellableStatuses)) {
            http_response_code(400);
            echo json_encode(["message" => "Order cannot be cancelled in its current status: " . $order['status']]);
            return;
        }

        // Update status to cancelled
        $updateQuery = "UPDATE orders SET status = 'cancelled' WHERE id = :id";
        $updateStmt = $db->prepare($updateQuery);
        $updateStmt->bindParam(":id", $orderId);

        if ($updateStmt->execute()) {
            echo json_encode(["message" => "Order cancelled successfully."]);
        }
        else {
            http_response_code(500);
            echo json_encode(["message" => "Failed to cancel order."]);
        }
    }
    catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["message" => "Error cancelling order: " . $e->getMessage()]);
    }
}

function updateOrderStatus($db, $data)
{
    if (empty($data->order_id) || empty($data->status)) {
        http_response_code(400);
        echo json_encode(["message" => "Order ID and Status are required."]);
        return;
    }

    $allowedStatuses = ['pending', 'order accepted', 'processing', 'shipped', 'out for delivery', 'delivered', 'cancelled'];
    if (!in_array($data->status, $allowedStatuses)) {
        http_response_code(400);
        echo json_encode(["message" => "Invalid status value."]);
        return;
    }

    try {
        // [BLOCKER] - Irreversible Cancellation
        // First check current status
        $statusCheckQuery = "SELECT status FROM orders WHERE id = :id";
        $statusCheckStmt = $db->prepare($statusCheckQuery);
        $statusCheckStmt->bindParam(":id", $data->order_id);
        $statusCheckStmt->execute();
        $currentOrder = $statusCheckStmt->fetch(PDO::FETCH_ASSOC);

        if ($currentOrder && in_array(strtolower($currentOrder['status']), ['cancelled', 'delivered'])) {
            http_response_code(400);
            echo json_encode(["message" => "This order is " . $currentOrder['status'] . " and its status cannot be changed."]);
            return;
        }

        $query = "UPDATE orders SET status = :status WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":status", $data->status);
        $stmt->bindParam(":id", $data->order_id);

        if ($stmt->execute()) {
            echo json_encode(["message" => "Order status updated successfully."]);
        }
        else {
            http_response_code(500);
            echo json_encode(["message" => "Failed to update status."]);
        }
    }
    catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["message" => "Error updating status: " . $e->getMessage()]);
    }


}
