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
require_once 'mailer.php';

$database = new Database();
$db = $database->getConnection();

if ($db === null) {
    $errorMsg = 'Database connection failed.';
    logDebug('Error: ' . $errorMsg);
    http_response_code(500);
    echo json_encode(['message' => $errorMsg]);
    exit();
}

/**
 * Deduct stock atomically for an order when payment is confirmed.
 * Idempotent: checks and sets stock_deducted = 1 so it runs at most once.
 */
function deductOrderStock($db, $orderId)
{
    try {
        $checkStmt = $db->prepare("SELECT stock_deducted, payment_status FROM orders WHERE id = :id LIMIT 1");
        $checkStmt->execute([':id' => $orderId]);
        $order = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            return false;
        }

        // If already deducted, don't deduct again
        if (!empty($order['stock_deducted']) && (int)$order['stock_deducted'] === 1) {
            return true;
        }

        $itemQuery = "SELECT product_id, quantity FROM order_items WHERE order_id = :order_id";
        $itemStmt = $db->prepare($itemQuery);
        $itemStmt->execute([':order_id' => $orderId]);
        $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

        $isInternalProxyId = function($id) {
            $str = (string)$id;
            return strpos($str, 'exp-') === 0 
                || strpos($str, 'surprise-') === 0 
                || strpos($str, 'sub-') === 0 
                || strpos($str, 'subscription-') === 0;
        };

        foreach ($items as $item) {
            if (!$isInternalProxyId($item['product_id'])) {
                $deductQuery = "UPDATE products
                                SET stock_quantity = GREATEST(0, stock_quantity - :qty1),
                                    stock_status = IF(stock_quantity - :qty2 <= 0, 'out_of_stock', 'in_stock')
                                WHERE id = :id AND category NOT IN ('surprise_experience', 'subscription')";
                $deductStmt = $db->prepare($deductQuery);
                $deductStmt->bindParam(":qty1", $item['quantity'], PDO::PARAM_INT);
                $deductStmt->bindParam(":qty2", $item['quantity'], PDO::PARAM_INT);
                $deductStmt->bindParam(":id", $item['product_id']);
                $deductStmt->execute();
            }
        }

        $markStmt = $db->prepare("UPDATE orders SET stock_deducted = 1 WHERE id = :id");
        $markStmt->execute([':id' => $orderId]);
        return true;
    } catch (Exception $e) {
        error_log("deductOrderStock error for order {$orderId}: " . $e->getMessage());
        return false;
    }
}

/**
 * Restore stock atomically if an order is cancelled or payment failed.
 * Idempotent: sets stock_deducted = 0 so it restores at most once.
 */
function restoreOrderStock($db, $orderId, $force = false)
{
    try {
        $checkStmt = $db->prepare("SELECT stock_deducted, status, payment_status FROM orders WHERE id = :id LIMIT 1");
        $checkStmt->execute([':id' => $orderId]);
        $order = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            return false;
        }

        // Only restore if stock was marked deducted (or force is true for legacy pending cleanup)
        if (!$force && (empty($order['stock_deducted']) || (int)$order['stock_deducted'] === 0)) {
            return true;
        }

        $itemQuery = "SELECT product_id, quantity FROM order_items WHERE order_id = :order_id";
        $itemStmt = $db->prepare($itemQuery);
        $itemStmt->execute([':order_id' => $orderId]);
        $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

        $isInternalProxyId = function($id) {
            $str = (string)$id;
            return strpos($str, 'exp-') === 0 
                || strpos($str, 'surprise-') === 0 
                || strpos($str, 'sub-') === 0 
                || strpos($str, 'subscription-') === 0;
        };

        foreach ($items as $item) {
            if (!$isInternalProxyId($item['product_id'])) {
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
        }

        $markStmt = $db->prepare("UPDATE orders SET stock_deducted = 0 WHERE id = :id");
        $markStmt->execute([':id' => $orderId]);
        return true;
    } catch (Exception $e) {
        error_log("restoreOrderStock error for order {$orderId}: " . $e->getMessage());
        return false;
    }
}

/**
 * Dispatches confirmed order WhatsApp notification to Admin (Only on Paid Orders)
 */
function sendOrderWhatsAppNotification($db, $orderId)
{
    try {
        $stmt = $db->prepare("SELECT * FROM orders WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) return false;

        $admin_whatsapp = "917353363881";
        $apikey = getenv('CALLMEBOT_APIKEY') ?: ($_ENV['CALLMEBOT_APIKEY'] ?? ($_SERVER['CALLMEBOT_APIKEY'] ?? ''));
        if (empty($apikey)) return false;

        $msg_name    = $order['customer_name'] ?? 'Client';
        $msg_phone   = $order['customer_phone'] ?? 'N/A';
        $msg_address = $order['delivery_address'] ?? 'N/A';
        $msg_total   = number_format((float)$order['total_amount'], 2);
        $msg_date    = $order['delivery_date'] ?? 'N/A';
        $msg_time    = $order['delivery_time'] ?? 'N/A';
        $msg_created = $order['created_at'] ?? date('Y-m-d H:i:s');

        $message = "🎉 *Confirmed Paid Order*\n\n" .
            "📋 *Order ID:* #" . substr($orderId, 0, 8) . "\n" .
            "Customer: $msg_name\n" .
            "Phone: $msg_phone\n" .
            "Total Amount: ₹$msg_total\n" .
            "Delivery Date: $msg_date ($msg_time)\n" .
            "Address: $msg_address\n\n" .
            "Payment Status: PAID via Cashfree\n" .
            "Order Time: $msg_created";

        $encoded_message = urlencode($message);
        $url = "https://api.callmebot.com/whatsapp.php?phone=$admin_whatsapp&text=$encoded_message&apikey=$apikey";
        @file_get_contents($url);
        return true;
    } catch (Exception $e) {
        error_log("WhatsApp alert error: " . $e->getMessage());
        return false;
    }
}

// Ensure orders table has payment, email, and stock tracking columns
function ensureOrderSchema($db)
{
    try {
        $cols = $db->query("SHOW COLUMNS FROM orders")->fetchAll(PDO::FETCH_COLUMN);
        $needed = [
            'payment_status'     => "VARCHAR(50) DEFAULT 'pending'",
            'payment_session_id' => "VARCHAR(255) DEFAULT NULL",
            'cf_order_id'        => "VARCHAR(100) DEFAULT NULL",
            'transaction_id'     => "VARCHAR(100) DEFAULT NULL",
            'email_sent'         => "TINYINT(1) DEFAULT 0",
            'stock_deducted'     => "TINYINT(1) DEFAULT 0"
        ];
        foreach ($needed as $col => $def) {
            if (!in_array($col, $cols, true)) {
                $db->exec("ALTER TABLE orders ADD COLUMN `$col` $def");
            }
        }

        // Set stock_deducted = 1 on already paid orders
        $db->exec("UPDATE orders SET stock_deducted = 1 WHERE payment_status = 'paid' AND (stock_deducted IS NULL OR stock_deducted = 0)");

        // Auto-cleanup stale abandoned pending orders older than 15 minutes:
        // Restores their stock if deducted under old logic, and marks them cancelled/failed
        $staleStmt = $db->query(
            "SELECT id FROM orders 
             WHERE status = 'pending' 
               AND (payment_status = 'pending' OR payment_status IS NULL)
               AND created_at < (NOW() - INTERVAL 15 MINUTE)"
        );
        if ($staleStmt) {
            $staleOrders = $staleStmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($staleOrders as $stale) {
                restoreOrderStock($db, $stale['id'], true);
                $updStale = $db->prepare("UPDATE orders SET status = 'cancelled', payment_status = 'failed', stock_deducted = 0 WHERE id = :id");
                $updStale->execute([':id' => $stale['id']]);
            }
        }
    } catch (Exception $e) {
        error_log("Order schema check notice: " . $e->getMessage());
    }
}
ensureOrderSchema($db);

/**
 * Dispatches Customer Confirmation Email and Store Admin Notification Email
 */
function dispatchOrderConfirmationAndAlertEmails($db, $orderId)
{
    try {
        $stmt = $db->prepare("SELECT * FROM orders WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            return false;
        }

        // Avoid duplicate emails
        if (!empty($order['email_sent']) && (int)$order['email_sent'] === 1) {
            return true;
        }

        // Fetch order items with product details
        $itemStmt = $db->prepare(
            "SELECT oi.*, p.name, p.image 
             FROM order_items oi 
             LEFT JOIN products p ON oi.product_id = p.id 
             WHERE oi.order_id = :id"
        );
        $itemStmt->execute([':id' => $orderId]);
        $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

        // 1. Send Customer Confirmation Email
        if (!empty($order['customer_email'])) {
            $customerSubject = "Order Confirmed #" . substr($orderId, 0, 8) . " | Oh My Gudness";
            $customerHtml = buildOrderConfirmationEmail($order, $items);
            @sendEmail($order['customer_email'], $customerSubject, $customerHtml);
        }

        // 2. Send Admin Notification Email
        $adminEmail = omgGetEnv('ADMIN_NOTIFICATION_EMAIL', 'info@ohmygudness.in');
        if (!empty($adminEmail)) {
            $adminSubject = "🚨 [NEW ORDER] ₹" . number_format((float)$order['total_amount']) . " from " . ($order['customer_name'] ?? 'Client');
            $adminHtml = buildAdminOrderNotificationEmail($order, $items);
            @sendEmail($adminEmail, $adminSubject, $adminHtml);
        }

        // Mark email_sent = 1
        $upd = $db->prepare("UPDATE orders SET email_sent = 1 WHERE id = :id");
        $upd->execute([':id' => $orderId]);
        return true;
    } catch (Exception $e) {
        error_log("Order notification dispatch error: " . $e->getMessage());
        return false;
    }
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
    case 'verify_payment':
        $userId = authenticate();
        verifyPayment($db, $userId, $data);
        break;
    case 'cashfree_status':
        $cfConfig = getCashfreeConfig();
        $isConfigured = !empty($cfConfig['app_id']) && !empty($cfConfig['secret_key']);
        $smtpConfig = function_exists('getSmtpConfig') ? getSmtpConfig() : [];
        $smtpActive = !empty($smtpConfig['host']) && !empty($smtpConfig['user']) && !empty($smtpConfig['pass']);

        echo json_encode([
            "status" => "ok",
            "cashfree_configured" => $isConfigured,
            "environment" => $cfConfig['env'],
            "base_url" => $cfConfig['base_url'],
            "app_id_detected" => !empty($cfConfig['app_id']),
            "app_id_preview" => !empty($cfConfig['app_id']) ? (substr($cfConfig['app_id'], 0, 8) . '...' . (strlen($cfConfig['app_id']) > 12 ? substr($cfConfig['app_id'], -4) : '')) : null,
            "secret_key_detected" => !empty($cfConfig['secret_key']),
            "smtp_configured" => $smtpActive,
            "smtp_user_preview" => !empty($smtpConfig['user']) ? $smtpConfig['user'] : null,
            "detection_sources" => [
                "server_has_app_id" => isset($_SERVER['CASHFREE_APP_ID']) && $_SERVER['CASHFREE_APP_ID'] !== '',
                "env_has_app_id" => isset($_ENV['CASHFREE_APP_ID']) && $_ENV['CASHFREE_APP_ID'] !== '',
                "getenv_has_app_id" => (getenv('CASHFREE_APP_ID') !== false && getenv('CASHFREE_APP_ID') !== ''),
            ]
        ]);
        break;
    case 'cashfree_webhook':
        handleCashfreeWebhook($db);
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

        $query = "INSERT INTO orders (id, user_id, total_amount, customer_name, customer_email, customer_phone, delivery_address, delivery_option, delivery_date, delivery_time, payment_method, status, payment_status, stock_deducted) VALUES (:id, :user_id, :total_amount, :customer_name, :customer_email, :customer_phone, :delivery_address, :delivery_option, :delivery_date, :delivery_time, :payment_method, 'pending', 'pending', 0)";

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

        // Create Order Items (Stock will be deducted atomically ONLY upon successful payment)
        foreach ($data->items as $item) {
            $itemQuery = "INSERT INTO order_items (order_id, product_id, quantity, unit_price) VALUES (:order_id, :product_id, :quantity, :unit_price)";
            $itemStmt = $db->prepare($itemQuery);
            $itemStmt->bindParam(":order_id", $uuid);
            $itemStmt->bindParam(":product_id", $item->product_id);
            $itemStmt->bindParam(":quantity", $item->quantity);
            $itemStmt->bindParam(":unit_price", $item->unit_price);
            $itemStmt->execute();
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

        // ── CASHFREE PAYMENT GATEWAY INITIALIZATION ────────────────────────────
        // Note: WhatsApp admin alert and confirmation emails are dispatched ONLY upon confirmed payment.
        $paymentSessionId = null;
        $cfOrderId = null;
        $cfConfig = getCashfreeConfig();
        $isCashfreeActive = !empty($cfConfig['app_id']) && !empty($cfConfig['secret_key']);

        if (!$isCashfreeActive) {
            throw new Exception("Cashfree payment credentials are not configured on the server. Please verify CASHFREE_APP_ID and CASHFREE_SECRET_KEY under Hostinger > Websites > Environment variables.");
        }

        if ($isCashfreeActive) {
            $cleanPhone = preg_replace('/[^0-9]/', '', (string)$data->customer_phone);
            if (strlen($cleanPhone) > 10) {
                $cleanPhone = substr($cleanPhone, -10);
            }
            if (strlen($cleanPhone) < 10) {
                $cleanPhone = '9999999999';
            }

            // Alphanumeric order id (max 45 chars)
            $cfClientOrderId = substr('omg_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $uuid), 0, 45);

            $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : (isset($_SERVER['HTTP_REFERER']) ? parse_url($_SERVER['HTTP_REFERER'], PHP_URL_SCHEME) . '://' . parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST) : 'https://ohmygudness.in');
            $returnUrl = rtrim($origin, '/') . '/checkout?order_id=' . urlencode($uuid);

            $cfPayload = [
                'order_id' => $cfClientOrderId,
                'order_amount' => round((float)$data->total_amount, 2),
                'order_currency' => 'INR',
                'customer_details' => [
                    'customer_id' => (string)($userId ? preg_replace('/[^a-zA-Z0-9_-]/', '', $userId) : 'cust_' . substr(md5($data->customer_email), 0, 10)),
                    'customer_name' => (string)($data->customer_name ?: 'Valued Customer'),
                    'customer_email' => (string)($data->customer_email ?: 'guest@ohmygudness.in'),
                    'customer_phone' => $cleanPhone
                ],
                'order_meta' => [
                    'return_url' => $returnUrl
                ]
            ];

            $ch = curl_init($cfConfig['base_url'] . '/orders');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($cfPayload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'x-client-id: ' . $cfConfig['app_id'],
                'x-client-secret: ' . $cfConfig['secret_key'],
                'x-api-version: ' . $cfConfig['api_version'],
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 20);
            $cfRes = curl_exec($ch);
            $cfHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $cfErrno = curl_errno($ch);
            $cfErrMsg = curl_error($ch);
            curl_close($ch);

            if ($cfErrno) {
                error_log('Cashfree cURL error: ' . $cfErrMsg);
                throw new Exception("Payment Gateway Connection Error: " . $cfErrMsg);
            }

            if ($cfRes && ($cfHttp === 200 || $cfHttp === 201)) {
                $cfData = json_decode($cfRes, true);
                if (!empty($cfData['payment_session_id'])) {
                    $paymentSessionId = $cfData['payment_session_id'];
                    $cfOrderId = $cfData['cf_order_id'] ?? $cfClientOrderId;

                    $updCfStmt = $db->prepare(
                        "UPDATE orders 
                         SET payment_session_id = :sess, cf_order_id = :cfid, payment_method = 'cashfree' 
                         WHERE id = :id"
                    );
                    $updCfStmt->execute([
                        ':sess' => $paymentSessionId,
                        ':cfid' => (string)$cfOrderId,
                        ':id'   => $uuid
                    ]);
                } else {
                    throw new Exception("Cashfree did not return a valid payment session ID.");
                }
            } else {
                error_log('Cashfree order creation error: ' . $cfRes);
                $cfErr = json_decode($cfRes, true);
                $errMsg = !empty($cfErr['message']) ? $cfErr['message'] : 'Payment gateway rejected order creation.';
                throw new Exception("Payment Gateway Error: " . $errMsg);
            }
        }

        $db->commit();

        if (!$isCashfreeActive) {
            dispatchOrderConfirmationAndAlertEmails($db, $uuid);
        }

        echo json_encode([
            "id" => $uuid,
            "status" => "pending",
            "payment_session_id" => $paymentSessionId,
            "cf_order_id" => $cfOrderId,
            "environment" => $cfConfig['env'],
            "cashfree_active" => $isCashfreeActive && !empty($paymentSessionId),
            "whatsapp_sent" => ($result !== false)
        ]);
    }
    catch (Exception $e) {
        $db->rollBack();
        http_response_code(400); // 400 for validation logic/stock issues
        echo json_encode(["message" => "Failed to create order: " . $e->getMessage()]);
    }
}

/**
 * Verify payment status with Cashfree
 */
function verifyPayment($db, $userId, $data)
{
    $orderId = isset($_GET['id']) ? trim($_GET['id']) : (!empty($data->order_id) ? trim($data->order_id) : '');
    if (empty($orderId)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Order ID is required."]);
        exit();
    }

    $stmt = $db->prepare("SELECT * FROM orders WHERE id = :id AND user_id = :uid LIMIT 1");
    $stmt->execute([':id' => $orderId, ':uid' => $userId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "Order not found."]);
        exit();
    }

    if (($order['payment_status'] ?? '') === 'paid') {
        echo json_encode([
            "success" => true,
            "message" => "Order already confirmed and paid.",
            "order_id" => $orderId,
            "status" => $order['status'],
            "payment_status" => "paid",
            "transaction_id" => $order['transaction_id'] ?? null
        ]);
        exit();
    }

    $cfConfig = getCashfreeConfig();
    $isPaid = false;
    $transactionId = null;

    if (!empty($cfConfig['app_id']) && !empty($cfConfig['secret_key'])) {
        $lookupOrderId = !empty($order['cf_order_id']) && !is_numeric($order['cf_order_id']) 
            ? $order['cf_order_id'] 
            : substr('omg_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $orderId), 0, 45);

        $paymentsUrl = $cfConfig['base_url'] . '/orders/' . urlencode($lookupOrderId) . '/payments';
        $ch = curl_init($paymentsUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'x-client-id: ' . $cfConfig['app_id'],
            'x-client-secret: ' . $cfConfig['secret_key'],
            'x-api-version: ' . $cfConfig['api_version'],
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $payments = json_decode($res, true);
        if ($httpCode === 200 && is_array($payments)) {
            foreach ($payments as $p) {
                if (isset($p['payment_status']) && strtoupper($p['payment_status']) === 'SUCCESS') {
                    $isPaid = true;
                    $transactionId = $p['cf_payment_id'] ?? ($p['bank_reference'] ?? null);
                    break;
                }
            }
        }

        // Secondary check on order status
        if (!$isPaid) {
            $orderUrl = $cfConfig['base_url'] . '/orders/' . urlencode($lookupOrderId);
            $ch2 = curl_init($orderUrl);
            curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch2, CURLOPT_HTTPHEADER, [
                'x-client-id: ' . $cfConfig['app_id'],
                'x-client-secret: ' . $cfConfig['secret_key'],
                'x-api-version: ' . $cfConfig['api_version'],
                'Content-Type: application/json'
            ]);
            curl_setopt($ch2, CURLOPT_TIMEOUT, 15);
            $res2 = curl_exec($ch2);
            $httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
            curl_close($ch2);

            $orderInfo = json_decode($res2, true);
            if ($httpCode2 === 200 && isset($orderInfo['order_status']) && strtoupper($orderInfo['order_status']) === 'PAID') {
                $isPaid = true;
                $transactionId = $orderInfo['cf_order_id'] ?? null;
            }
        }
    } else {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "message" => "Cashfree credentials are not configured on the server. Please verify CASHFREE_APP_ID and CASHFREE_SECRET_KEY under Hostinger > Websites > Environment variables."
        ]);
        exit();
    }

    if ($isPaid) {
        $upd = $db->prepare(
            "UPDATE orders 
             SET payment_status = 'paid', status = 'confirmed', payment_method = 'cashfree', transaction_id = :tx 
             WHERE id = :id"
        );
        $upd->execute([':tx' => (string)$transactionId, ':id' => $orderId]);

        // 1. Deduct stock atomically upon confirmed payment
        deductOrderStock($db, $orderId);

        // 2. Dispatch Customer Confirmation & Store Admin Notification Emails
        dispatchOrderConfirmationAndAlertEmails($db, $orderId);

        // 3. Dispatch confirmed order WhatsApp notification to Admin
        sendOrderWhatsAppNotification($db, $orderId);

        echo json_encode([
            "success" => true,
            "message" => "Payment verified successfully.",
            "order_id" => $orderId,
            "status" => "confirmed",
            "payment_status" => "paid",
            "transaction_id" => $transactionId
        ]);
        exit();
    } else {
        echo json_encode([
            "success" => false,
            "message" => "Payment not completed or failed.",
            "order_id" => $orderId,
            "payment_status" => $order['payment_status'] ?? 'pending'
        ]);
        exit();
    }
}

/**
 * Handle asynchronous Cashfree webhook notifications
 */
function handleCashfreeWebhook($db)
{
    $rawPayload = file_get_contents("php://input");
    $event = json_decode($rawPayload, true);

    if (!$event || (!isset($event['type']) && !isset($event['event']))) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Invalid webhook payload"]);
        exit();
    }

    $eventType = strtoupper($event['type'] ?? ($event['event'] ?? ''));
    $orderData = $event['data']['order'] ?? ($event['order'] ?? []);
    $paymentData = $event['data']['payment'] ?? ($event['payment'] ?? []);
    $cfOrderId = $orderData['order_id'] ?? ($event['order_id'] ?? null);
    $txId = $paymentData['cf_payment_id'] ?? ($event['cf_payment_id'] ?? ($paymentData['bank_reference'] ?? null));
    $paymentStatus = strtoupper($paymentData['payment_status'] ?? ($event['payment_status'] ?? ''));

    $isSuccess = in_array($eventType, ['PAYMENT_SUCCESS_WEBHOOK', 'PAYMENT_SUCCESS', 'ORDER_PAID', 'PAYMENT.SUCCESS', 'ORDER.PAID'], true) 
                 || $paymentStatus === 'SUCCESS';

    if ($isSuccess && $cfOrderId) {
        $stmt = $db->prepare(
            "UPDATE orders 
             SET payment_status = 'paid', status = 'confirmed', payment_method = 'cashfree', transaction_id = :tx 
             WHERE cf_order_id = :cfid OR id = :cfid OR id = REPLACE(:cfid, 'omg_', '')"
        );
        $stmt->execute([':tx' => (string)$txId, ':cfid' => $cfOrderId]);

        // Dispatch Customer Confirmation & Store Admin Notification Emails, WhatsApp & Deduct Stock
        $findStmt = $db->prepare("SELECT id FROM orders WHERE cf_order_id = :cfid OR id = :cfid OR id = REPLACE(:cfid, 'omg_', '') LIMIT 1");
        $findStmt->execute([':cfid' => $cfOrderId]);
        $foundOrder = $findStmt->fetch(PDO::FETCH_ASSOC);
        if ($foundOrder && !empty($foundOrder['id'])) {
            deductOrderStock($db, $foundOrder['id']);
            dispatchOrderConfirmationAndAlertEmails($db, $foundOrder['id']);
            sendOrderWhatsAppNotification($db, $foundOrder['id']);
        }
    } else {
        // Handle failed or user dropped payment webhook events
        $isFailedOrDropped = in_array($eventType, [
            'PAYMENT_FAILED_WEBHOOK', 'PAYMENT_FAILED', 'PAYMENT.FAILED',
            'PAYMENT_USER_DROPPED_WEBHOOK', 'USER_DROPPED_WEBHOOK', 'USER_DROPPED', 'PAYMENT_DROPPED',
            'ORDER_TERMINATED_WEBHOOK', 'ORDER_CANCELLED', 'ORDER.CANCELLED'
        ], true) || in_array($paymentStatus, ['FAILED', 'USER_DROPPED', 'CANCELLED'], true);

        if ($isFailedOrDropped && $cfOrderId) {
            $findStmt = $db->prepare("SELECT id, payment_status, stock_deducted FROM orders WHERE cf_order_id = :cfid OR id = :cfid OR id = REPLACE(:cfid, 'omg_', '') LIMIT 1");
            $findStmt->execute([':cfid' => $cfOrderId]);
            $foundOrder = $findStmt->fetch(PDO::FETCH_ASSOC);
            if ($foundOrder && $foundOrder['payment_status'] !== 'paid') {
                restoreOrderStock($db, $foundOrder['id']);
                $failStmt = $db->prepare("UPDATE orders SET payment_status = 'failed', status = 'cancelled' WHERE id = :id");
                $failStmt->execute([':id' => $foundOrder['id']]);
            }
        }
    }

    echo json_encode(["status" => "ok"]);
    exit();
}

function getUserOrders($db, $userId)
{
    // Return only confirmed/paid orders for the customer view.
    // Unpaid checkout drafts where payment was abandoned or failed must never appear.
    $query = "SELECT * FROM orders 
              WHERE user_id = :user_id 
                AND (payment_status = 'paid' OR (payment_method != 'cashfree' AND status != 'cancelled'))
              ORDER BY created_at DESC";
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
