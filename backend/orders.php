<?php
// Enable error reporting (display off; errors logged server-side)
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// FEATURE 4: USE SYSTEM TIME FOR ORDERS
date_default_timezone_set('Asia/Kolkata');

// Debug logging — only active when APP_DEBUG=true in .env
// WARNING: Never enable in production; logs include request headers (auth tokens).
$_appDebug = (strtolower((string)(getenv('APP_DEBUG') ?: '')) === 'true');
function logDebug($message)
{
    global $_appDebug;
    if (!$_appDebug) return;
    $logFile = __DIR__ . '/debug_orders.log';
    $entry = date('Y-m-d H:i:s') . ' - ' . $message . "\n";
    file_put_contents($logFile, $entry, FILE_APPEND);
}

logDebug('Request received: ' . $_SERVER['REQUEST_URI']);
logDebug('Method: ' . $_SERVER['REQUEST_METHOD']);

require_once 'config.php';

$database = new Database();
$db = $database->getConnection();

if ($db === null) {
    $errorMsg = 'Database connection failed.';
    logDebug('Error: ' . $errorMsg);
    http_response_code(500);
    echo json_encode(['message' => $errorMsg]);
    exit();
}

// Check for simple connectivity test
if (isset($_GET['action']) && $_GET['action'] === 'test') {
    logDebug('Test action received.');
    echo json_encode(['status' => 'success', 'message' => 'Backend is reachable']);
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
    case 'bulk_archive':
        // BULK ARCHIVE ORDERS (ADMIN ONLY) - SOFT DELETE, NEVER HARD DELETE
        if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
            http_response_code(401);
            echo json_encode(["message" => "Unauthorized - Please login as Admin"]);
            exit();
        }
        bulkArchiveOrders($db, $data);
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

        // ── ITEM TYPE DETECTION HELPER ────────────────────────────────────────────
        $isInternalProxyId = function($id) {
            $str = (string)$id;
            return strpos($str, 'exp-') === 0 
                || strpos($str, 'surprise-') === 0 
                || strpos($str, 'sub-') === 0 
                || strpos($str, 'subscription-') === 0;
        };

        // ── INVENTORY & STOCK RULES ───────────────────────────────────────────────
        // 1. Subscriptions & Surprise Experiences do NOT require stock, do NOT have
        //    inventory deducted, and are NEVER blocked by stock count.
        // 2. Normal physical products undergo SELECT ... FOR UPDATE, stock validation,
        //    and active status checks.
        foreach ($data->items as $item) {
            $productIdStr = (string)$item->product_id;

            // Branch A: Surprise Experience item
            if (strpos($productIdStr, 'exp-') === 0 || strpos($productIdStr, 'surprise-') === 0) {
                // Ensure proxy product row exists with category 'surprise_experience' (never 'occasions')
                $expId = null;
                if (preg_match('/^exp-(\d+)-/', $productIdStr, $expMatch)) {
                    $expId = (int)$expMatch[1];
                }

                $realExp = null;
                if ($expId !== null) {
                    $expLookup = $db->prepare("SELECT * FROM surprise_experiences WHERE id = ? LIMIT 1");
                    $expLookup->execute([$expId]);
                    $realExp = $expLookup->fetch(PDO::FETCH_ASSOC) ?: null;
                }

                $expName  = $realExp ? $realExp['title'] : (!empty($item->name) ? $item->name : 'Bespoke Experience Reservation');
                $expImage = $realExp ? $realExp['image'] : null;
                $expDesc  = $realExp ? ($realExp['description'] ?? 'Bespoke Experience Reservation with on-site coordination & styling') : 'Bespoke Experience Reservation with on-site coordination & styling';
                $expPrice = $realExp ? (float)$realExp['base_price'] : (float)$item->unit_price;
                $expSlug  = 'exp-' . strtolower(preg_replace('/[^a-z0-9]+/i', '-', $expName)) . '-' . substr(md5($productIdStr), 0, 6);

                $insProduct = $db->prepare(
                    "INSERT IGNORE INTO products
                     (id, name, slug, description, price, category, image, is_active, stock_status, stock_quantity)
                     VALUES (:id, :name, :slug, :desc, :price, 'surprise_experience', :image, 1, 'in_stock', 0)"
                );
                $insProduct->execute([
                    ':id'    => $productIdStr,
                    ':name'  => $expName,
                    ':slug'  => $expSlug,
                    ':desc'  => $expDesc,
                    ':price' => $expPrice,
                    ':image' => $expImage,
                ]);
                continue; // Unlimited availability - bypass stock validation!
            }

            // Branch B: Subscription item
            if (strpos($productIdStr, 'sub-') === 0 || strpos($productIdStr, 'subscription-') === 0) {
                $subPlansStmt = $db->query("SELECT * FROM subscription_plans");
                $subPlansList = $subPlansStmt ? $subPlansStmt->fetchAll(PDO::FETCH_ASSOC) : [];
                $matchedPlan = null;
                foreach ($subPlansList as $sp) {
                    if (strpos($productIdStr, 'sub-' . $sp['slug']) === 0 || strpos($productIdStr, $sp['slug']) !== false) {
                        $matchedPlan = $sp;
                        break;
                    }
                }
                if (!$matchedPlan && !empty($subPlansList)) {
                    $matchedPlan = $subPlansList[0];
                }

                $subName = !empty($item->name) ? $item->name : ($matchedPlan ? $matchedPlan['name'] . ' Subscription' : 'Floral Subscription Plan');
                $subSlug = 'sub-' . ($matchedPlan ? $matchedPlan['slug'] : 'plan') . '-' . substr(md5($productIdStr), 0, 6);
                $subPrice = (float)$item->unit_price;
                $subImage = $matchedPlan['image'] ?? null;
                $subDesc = $matchedPlan['description'] ?? 'OMG Luxury Floral Subscription recurring delivery';

                $insProduct = $db->prepare("INSERT IGNORE INTO products (id, name, slug, description, price, category, image, is_active, stock_status, stock_quantity) VALUES (:id, :name, :slug, :desc, :price, 'subscription', :image, 1, 'in_stock', 0)");
                $insProduct->execute([
                    ':id' => $productIdStr,
                    ':name' => $subName,
                    ':slug' => $subSlug,
                    ':desc' => $subDesc,
                    ':price' => $subPrice,
                    ':image' => $subImage
                ]);
                continue; // Not inventory-based - bypass stock validation!
            }

            // Branch C: Normal physical inventory products
            $stockQuery = "SELECT stock_status, stock_quantity, name, is_active, category FROM products WHERE id = :id FOR UPDATE";
            $stockStmt = $db->prepare($stockQuery);
            $stockStmt->bindParam(":id", $item->product_id);
            $stockStmt->execute();

            if ($stockStmt->rowCount() > 0) {
                $product = $stockStmt->fetch(PDO::FETCH_ASSOC);
                if ($product['category'] === 'surprise_experience' || $product['category'] === 'subscription') {
                    continue; // Skip if internal proxy row
                }
                if ((int)$product['is_active'] !== 1) {
                    throw new Exception("Product '" . $product['name'] . "' is no longer active.");
                }
                if ($product['stock_status'] === 'out_of_stock' || (int)$product['stock_quantity'] < $item->quantity) {
                    throw new Exception("Product '" . $product['name'] . "' has insufficient stock available (Requested: " . $item->quantity . ", Available: " . $product['stock_quantity'] . ").");
                }
            } else {
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

        // Create Order Items & Conditionally Deduct Stock
        foreach ($data->items as $item) {
            $itemQuery = "INSERT INTO order_items (order_id, product_id, quantity, unit_price) VALUES (:order_id, :product_id, :quantity, :unit_price)";
            $itemStmt = $db->prepare($itemQuery);
            $itemStmt->bindParam(":order_id", $uuid);
            $itemStmt->bindParam(":product_id", $item->product_id);
            $itemStmt->bindParam(":quantity", $item->quantity);
            $itemStmt->bindParam(":unit_price", $item->unit_price);
            $itemStmt->execute();

            // ── INVENTORY RULE: Only deduct stock for normal physical products. ────────
            // Subscriptions and Surprise Experiences are services without inventory.
            // Their proxy rows have stock_quantity = 0 and must NEVER be deducted from.
            if (!$isInternalProxyId((string)$item->product_id)) {
                // Deduct stock quantity atomically; use two distinct named params (:qty1, :qty2)
                // since some PDO drivers do not support the same named placeholder used twice.
                $deductQuery = "UPDATE products
                                SET stock_quantity = GREATEST(0, stock_quantity - :qty1),
                                    stock_status = IF(stock_quantity - :qty2 <= 0, 'out_of_stock', 'in_stock')
                                WHERE id = :id AND category NOT IN ('surprise_experience', 'subscription')";
                $deductStmt = $db->prepare($deductQuery);
                $deductStmt->bindParam(":qty1", $item->quantity, PDO::PARAM_INT);
                $deductStmt->bindParam(":qty2", $item->quantity, PDO::PARAM_INT);
                $deductStmt->bindParam(":id", $item->product_id);
                $deductStmt->execute();
            }
            // else: subscription/surprise item → no stock deduction
        }

        // Auto-create customer subscription record in `subscriptions` table
        foreach ($data->items as $item) {
            if (strpos((string)$item->product_id, 'sub-') === 0 || strpos((string)$item->product_id, 'subscription-') === 0) {
                // Find matching plan ID
                $planId = 1;
                $subPlansStmt = $db->query("SELECT id, slug FROM subscription_plans");
                $subPlansList = $subPlansStmt ? $subPlansStmt->fetchAll(PDO::FETCH_ASSOC) : [];
                if (isset($item->_subscription) && !empty($item->_subscription->plan_id)) {
                    $planId = (int)$item->_subscription->plan_id;
                } else {
                    foreach ($subPlansList as $sp) {
                        if (strpos((string)$item->product_id, 'sub-' . $sp['slug']) === 0 || strpos((string)$item->product_id, $sp['slug']) !== false) {
                            $planId = (int)$sp['id'];
                            break;
                        }
                    }
                    if (!$planId && !empty($subPlansList)) {
                        $planId = (int)$subPlansList[0]['id'];
                    }
                }

                // ── SUBSCRIPTION DUPLICATE PROTECTION ────────────────────────────────────
                // Backend independently verifies whether this user already has an active
                // subscription for the same plan. The frontend may pass confirm_duplicate=true
                // after the user explicitly confirmed they want another subscription.
                // The backend NEVER trusts the frontend's is_subscribed value — it always
                // queries the database directly.
                $confirmDuplicate = !empty($item->_subscription->confirm_duplicate) && $item->_subscription->confirm_duplicate === true;
                if (!$confirmDuplicate) {
                    $dupCheckStmt = $db->prepare(
                        "SELECT id FROM subscriptions WHERE user_id = ? AND plan_id = ? AND status = 'active' LIMIT 1"
                    );
                    $dupCheckStmt->execute([$userId, $planId]);
                    if ($dupCheckStmt->rowCount() > 0) {
                        $db->rollBack();
                        http_response_code(409);
                        echo json_encode([
                            "message"         => "duplicate_subscription",
                            "plan_id"         => $planId,
                            "error"           => "You already have an active subscription for this plan. Please confirm if you want to add another."
                        ]);
                        exit();
                    }
                }

                $subOccasionType   = !empty($item->_subscription->occasion_type)   ? $item->_subscription->occasion_type   : 'Special Occasion';
                $subOccasionDate   = !empty($item->_subscription->occasion_date)   ? $item->_subscription->occasion_date   : (!empty($data->delivery_date) ? $data->delivery_date : date('Y-m-d', strtotime('+7 days')));
                $subRecipientName  = !empty($item->_subscription->recipient_name)  ? $item->_subscription->recipient_name  : $data->customer_name;
                $subRecipientPhone = !empty($data->customer_phone)                 ? $data->customer_phone                 : '';

                // Calculate next delivery date
                $nextDelivery = $subOccasionDate;
                if (strtotime($nextDelivery) < time()) {
                    $nextDelivery = date('Y-m-d', strtotime('+1 year', strtotime($subOccasionDate)));
                }

                $newSubId = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                    mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                    mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
                    mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
                );

                $insSub = $db->prepare("INSERT INTO subscriptions
                    (id, user_id, plan_id, occasion_type, occasion_date, recipient_name, recipient_phone, delivery_address, city, status, next_delivery_date, total_deliveries, notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, 0, ?)");
                $insSub->execute([
                    $newSubId, $userId, $planId, $subOccasionType, $subOccasionDate,
                    $subRecipientName, $subRecipientPhone, $finalAddress, 'Bangalore',
                    $nextDelivery, "Purchased via Order #{$uuid}"
                ]);
            }
        }

        // Generate WhatsApp Notification Message
        $admin_whatsapp = "917353363881"; // Admin WhatsApp number
        // Read API key from env; set CALLMEBOT_APIKEY in .env to enable notifications
        $apikey = getenv('CALLMEBOT_APIKEY') ?: '';

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

        $db->beginTransaction();

        // Restore stock for each item in the cancelled order
        $itemQuery = "SELECT product_id, quantity FROM order_items WHERE order_id = :order_id";
        $itemStmt = $db->prepare($itemQuery);
        $itemStmt->bindParam(":order_id", $orderId);
        $itemStmt->execute();
        $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($items as $item) {
            // ── INVENTORY RULE: Only restore stock for normal physical products. ────────
            // Subscriptions and Surprise Experiences have no stock to restore.
            $cancelProductIdStr = (string)$item['product_id'];
            $cancelIsProxy = strpos($cancelProductIdStr, 'exp-') === 0
                          || strpos($cancelProductIdStr, 'surprise-') === 0
                          || strpos($cancelProductIdStr, 'sub-') === 0
                          || strpos($cancelProductIdStr, 'subscription-') === 0;
            if ($cancelIsProxy) {
                continue; // No stock to restore for subscription / surprise experience items
            }

            // Use two distinct named params (:qty1, :qty2) to avoid PDO duplicate
            // named parameter issues when the same placeholder appears twice.
            $restoreQuery = "UPDATE products
                             SET stock_quantity = stock_quantity + :qty1,
                                 stock_status = IF(stock_quantity + :qty2 > 0, 'in_stock', stock_status)
                             WHERE id = :id AND category NOT IN ('surprise_experience', 'subscription')";
            $restoreStmt = $db->prepare($restoreQuery);
            $restoreStmt->bindParam(":qty1", $item['quantity'], PDO::PARAM_INT);
            $restoreStmt->bindParam(":qty2", $item['quantity'], PDO::PARAM_INT);
            $restoreStmt->bindParam(":id", $item['product_id']);
            $restoreStmt->execute();
        }

        // Update order status to cancelled
        $updateQuery = "UPDATE orders SET status = 'cancelled' WHERE id = :id";
        $updateStmt = $db->prepare($updateQuery);
        $updateStmt->bindParam(":id", $orderId);

        if ($updateStmt->execute()) {
            $db->commit();
            echo json_encode(["message" => "Order cancelled successfully."]);
        } else {
            $db->rollBack();
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

/**
 * bulkArchiveOrders()
 *
 * Soft-archives one or more orders by setting is_archived = 1.
 * NEVER performs a DELETE operation on orders.
 * Customer order history (getUserOrders / getOrderById) is NOT affected
 * because those queries do not filter by is_archived.
 *
 * Required DB columns (added by migrate_archive.php):
 *   orders.is_archived  TINYINT(1) DEFAULT 0
 *   orders.archived_at  DATETIME   DEFAULT NULL
 *   orders.archived_by  VARCHAR(100) DEFAULT NULL
 */
function bulkArchiveOrders($db, $data)
{
    // Validate payload
    if (empty($data->order_ids) || !is_array($data->order_ids)) {
        http_response_code(400);
        echo json_encode(["message" => "order_ids array is required."]);
        return;
    }

    $orderIds = $data->order_ids;

    // Sanitize: only allow valid UUID-like strings (prevent SQL injection even with parameterized queries)
    $validIds = [];
    foreach ($orderIds as $id) {
        $clean = trim((string)$id);
        // UUID format: 8-4-4-4-12 hex chars
        if (preg_match('/^[0-9a-f\-]{32,36}$/i', $clean)) {
            $validIds[] = $clean;
        }
    }

    if (empty($validIds)) {
        http_response_code(400);
        echo json_encode(["message" => "No valid order IDs provided."]);
        return;
    }

    if (count($validIds) > 500) {
        http_response_code(400);
        echo json_encode(["message" => "Cannot archive more than 500 orders at once."]);
        return;
    }

    try {
        $archivedBy = $_SESSION['admin_username'] ?? 'admin';

        // Build parameterized IN clause
        $placeholders = implode(',', array_fill(0, count($validIds), '?'));

        // Verify all provided IDs exist in DB
        $checkStmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE id IN ($placeholders)");
        $checkStmt->execute($validIds);
        $foundCount = (int) $checkStmt->fetchColumn();

        if ($foundCount === 0) {
            http_response_code(404);
            echo json_encode(["message" => "None of the provided order IDs were found."]);
            return;
        }

        // SOFT ARCHIVE: UPDATE only — never DELETE
        $params = array_merge([$archivedBy], $validIds);
        $archiveStmt = $db->prepare(
            "UPDATE orders SET is_archived = 1, archived_at = NOW(), archived_by = ? WHERE id IN ($placeholders)"
        );
        $archiveStmt->execute($params);

        $archivedCount = $archiveStmt->rowCount();

        $noun = $archivedCount === 1 ? 'order' : 'orders';
        echo json_encode([
            "success"        => true,
            "archived_count" => $archivedCount,
            "message"        => "$archivedCount $noun archived successfully."
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["message" => "Failed to archive orders: " . $e->getMessage()]);
    }
}
