<?php

// Use existing database connection
require_once "config.php";

// WhatsApp numbers (with country code, no +)
$fromPHP = "+917795507828";
$admin_whatsapp = "+917353363881";


// Validate order_id
if (!isset($_GET['order_id']) || empty($_GET['order_id'])) {

    echo json_encode([
        "status" => "error",
        "message" => "order_id missing"
    ]);

    exit;
}

$order_id = $_GET['order_id'];


// Fetch order data
$sql = "
SELECT 
    user_id,
    total_amount,
    customer_name,
    customer_email,
    customer_phone,
    delivery_address,
    city,
    delivery_option,
    delivery_date,
    delivery_time,
    payment_method,
    created_at
FROM orders
WHERE id = ?
LIMIT 1
";

$stmt = $conn->prepare($sql);

if (!$stmt) {

    echo json_encode([
        "status" => "error",
        "message" => "Prepare failed"
    ]);

    exit;
}

$stmt->bind_param("s", $order_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {

    echo json_encode([
        "status" => "error",
        "message" => "Order not found"
    ]);

    exit;
}

$order = $result->fetch_assoc();


// Format message
$message = "🛒 New Order Received\n\n" .
    "📋 Order Details:\n" .
    "User ID: {$order['user_id']}\n" .
    "Customer Name: {$order['customer_name']}\n" .
    "Email: {$order['customer_email']}\n" .
    "Phone: {$order['customer_phone']}\n\n" .

    "📍 Delivery Address:\n" .
    "{$order['delivery_address']}\n" .
    "City: {$order['city']}\n\n" .

    "🚚 Delivery Option: {$order['delivery_option']}\n" .
    "📅 Delivery Date: {$order['delivery_date']}\n" .
    "⏰ Delivery Time: {$order['delivery_time']}\n\n" .

    "💳 Payment Method: {$order['payment_method']}\n" .
    "💰 Total Amount: ₹{$order['total_amount']}\n\n" .

    "🕒 Order Time: {$order['created_at']}";


// Encode message
$encoded_message = urlencode($message);


// Correct WhatsApp link format
$whatsapp_link = "https://web.whatsapp.com/send?phone=$admin_whatsapp&text=$encoded_message";


// Return JSON
header('Content-Type: application/json');

echo json_encode([
    "status" => "success",
    "from" => $fromPHP,
    "to" => $admin_whatsapp,
    "whatsapp_link" => $whatsapp_link
]);

$stmt->close();
$conn->close();

?>