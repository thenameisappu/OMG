<?php
/**
 * subscriptions.php — Occasion-Based Floral Subscription API
 * Actions: get_plans | create_subscription | get_subscriptions | cancel_subscription
 */

require_once 'config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$action = $_GET['action'] ?? '';

// ─────────────────────────────────────────────────────────────
// Helper: ensure subscription tables exist (auto-migrate)
// ─────────────────────────────────────────────────────────────
function ensureSubscriptionTables($pdo) {
    // subscription_plans
    $pdo->exec("CREATE TABLE IF NOT EXISTS `subscription_plans` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(255) NOT NULL,
        `slug` VARCHAR(100) UNIQUE NOT NULL,
        `tagline` VARCHAR(255),
        `description` TEXT,
        `frequency` ENUM('monthly','quarterly','yearly') NOT NULL,
        `deliveries_per_year` INT NOT NULL DEFAULT 12,
        `price_per_delivery` DECIMAL(10,2) NOT NULL,
        `total_price` DECIMAL(10,2) NOT NULL,
        `savings_percent` INT DEFAULT 0,
        `features` LONGTEXT,
        `is_popular` TINYINT(1) DEFAULT 0,
        `is_active` TINYINT(1) DEFAULT 1,
        `display_order` INT DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Seed plans if empty
    $count = $pdo->query("SELECT COUNT(*) FROM subscription_plans")->fetchColumn();
    if ((int)$count === 0) {
        $pdo->exec("INSERT INTO subscription_plans
            (name,slug,tagline,description,frequency,deliveries_per_year,price_per_delivery,total_price,savings_percent,features,is_popular,display_order)
            VALUES
            ('Monthly Bloom','monthly-bloom','Fresh joy, every month','Receive a curated luxury floral arrangement or hamper delivered to your loved one once a month, timed perfectly around your chosen occasion date.','monthly',12,999.00,11988.00,0,'{\"features\":[\"1 curated delivery per month\",\"Occasion-timed delivery\",\"Handpicked seasonal blooms\",\"Premium packaging & ribbon\",\"Digital occasion reminder\",\"Free delivery within Bangalore\"]}',0,1),
            ('Quarterly Celebration','quarterly-celebration','Four grand moments a year','Let us surprise your loved one four times a year with an exclusive curated hamper or luxury arrangement for each season of your special bond.','quarterly',4,1799.00,7196.00,20,'{\"features\":[\"1 premium delivery every quarter\",\"Larger luxury arrangements\",\"Seasonal exclusive hampers\",\"Personalized message card\",\"Photo delivery confirmation\",\"Free priority delivery\"]}',1,2),
            ('Annual Romance','annual-romance','The grandest single gesture','One extraordinary, over-the-top floral creation or premium hamper set once a year on your most special occasion — crafted as a true masterpiece.','yearly',1,3999.00,3999.00,33,'{\"features\":[\"1 grand annual delivery\",\"Bespoke signature arrangement\",\"Complimentary add-on upgrade\",\"Dedicated florist consultation\",\"Premium keepsake packaging\",\"Express same-day delivery option\"]}',0,3)");
    }

    // subscriptions
    $pdo->exec("CREATE TABLE IF NOT EXISTS `subscriptions` (
        `id` CHAR(36) PRIMARY KEY,
        `user_id` CHAR(36) NOT NULL,
        `plan_id` INT NOT NULL,
        `occasion_type` VARCHAR(100) NOT NULL,
        `occasion_date` DATE NOT NULL,
        `recipient_name` VARCHAR(255),
        `recipient_phone` VARCHAR(30),
        `delivery_address` TEXT,
        `city` VARCHAR(100) DEFAULT 'Bangalore',
        `status` ENUM('active','paused','cancelled','expired') DEFAULT 'active',
        `next_delivery_date` DATE,
        `total_deliveries` INT DEFAULT 0,
        `notes` TEXT,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (plan_id) REFERENCES subscription_plans(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// ─────────────────────────────────────────────────────────────
// Helper: get authenticated user from Bearer token
// ─────────────────────────────────────────────────────────────
function getAuthUser($pdo) {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) return null;
    $token = trim(substr($authHeader, 7));
    if (!$token) return null;

    // Decode JWT payload (no signature verify — using same pattern as rest of codebase)
    $parts = explode('.', $token);
    if (count($parts) < 2) return null;
    $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
    if (!isset($payload['user_id'])) return null;

    $stmt = $pdo->prepare("SELECT id, email FROM users WHERE id = ?");
    $stmt->execute([$payload['user_id']]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

// ─────────────────────────────────────────────────────────────
// Helper: generate UUID v4
// ─────────────────────────────────────────────────────────────
function generateUUID() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

// ─────────────────────────────────────────────────────────────
// Main dispatch
// ─────────────────────────────────────────────────────────────
try {
    $pdo = getDBConnection();
    ensureSubscriptionTables($pdo);

    // ── GET /subscriptions.php?action=get_plans ──────────────
    if ($action === 'get_plans' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $pdo->query("SELECT * FROM subscription_plans WHERE is_active = 1 ORDER BY display_order ASC");
        $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($plans as &$p) {
            if ($p['features']) {
                $decoded = json_decode($p['features'], true);
                // Support both {features:[...]} and direct [...] formats
                $p['features_list'] = is_array($decoded)
                    ? (isset($decoded['features']) ? $decoded['features'] : $decoded)
                    : [];
            } else {
                $p['features_list'] = [];
            }
            $p['price_per_delivery'] = (float)$p['price_per_delivery'];
            $p['total_price'] = (float)$p['total_price'];
            $p['savings_percent'] = (int)$p['savings_percent'];
            $p['is_popular'] = (bool)$p['is_popular'];
        }
        echo json_encode(['success' => true, 'plans' => $plans]);
        exit;
    }

    // ── POST /subscriptions.php?action=create_subscription ──
    if ($action === 'create_subscription' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $user = getAuthUser($pdo);
        if (!$user) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Authentication required to subscribe.']);
            exit;
        }

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $planId         = intval($body['plan_id'] ?? 0);
        $occasionType   = trim($body['occasion_type'] ?? '');
        $occasionDate   = trim($body['occasion_date'] ?? '');
        $recipientName  = trim($body['recipient_name'] ?? '');
        $recipientPhone = trim($body['recipient_phone'] ?? '');
        $address        = trim($body['delivery_address'] ?? '');
        $city           = trim($body['city'] ?? 'Bangalore');
        $notes          = trim($body['notes'] ?? '');

        if (!$planId || !$occasionType || !$occasionDate) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Plan, occasion type and occasion date are required.']);
            exit;
        }

        // Validate plan exists
        $planStmt = $pdo->prepare("SELECT * FROM subscription_plans WHERE id = ? AND is_active = 1");
        $planStmt->execute([$planId]);
        $plan = $planStmt->fetch(PDO::FETCH_ASSOC);
        if (!$plan) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Selected subscription plan not found.']);
            exit;
        }

        // Calculate next delivery date based on occasion date and frequency
        $oDate = new DateTime($occasionDate);
        $today = new DateTime();
        $nextDelivery = clone $oDate;
        $nextDelivery->setDate((int)$today->format('Y'), (int)$oDate->format('m'), (int)$oDate->format('d'));
        if ($nextDelivery <= $today) {
            $nextDelivery->modify('+1 year');
        }

        $subId = generateUUID();
        $stmt = $pdo->prepare("INSERT INTO subscriptions
            (id, user_id, plan_id, occasion_type, occasion_date, recipient_name, recipient_phone, delivery_address, city, next_delivery_date, notes, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')");
        $stmt->execute([
            $subId, $user['id'], $planId, $occasionType, $occasionDate,
            $recipientName, $recipientPhone, $address, $city,
            $nextDelivery->format('Y-m-d'), $notes
        ]);

        echo json_encode([
            'success'           => true,
            'subscription_id'   => $subId,
            'message'           => 'Subscription created successfully! Your first delivery is scheduled for ' . $nextDelivery->format('d M Y') . '.',
            'next_delivery'     => $nextDelivery->format('Y-m-d'),
            'plan'              => $plan['name'],
        ]);
        exit;
    }

    // ── GET /subscriptions.php?action=get_subscriptions ──────
    if ($action === 'get_subscriptions' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $user = getAuthUser($pdo);
        if (!$user) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Authentication required.']);
            exit;
        }

        $stmt = $pdo->prepare("
            SELECT s.*, p.name AS plan_name, p.frequency, p.price_per_delivery, p.total_price
            FROM subscriptions s
            JOIN subscription_plans p ON s.plan_id = p.id
            WHERE s.user_id = ?
            ORDER BY s.created_at DESC
        ");
        $stmt->execute([$user['id']]);
        $subs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'subscriptions' => $subs]);
        exit;
    }

    // ── POST /subscriptions.php?action=cancel_subscription ───
    if ($action === 'cancel_subscription' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $user = getAuthUser($pdo);
        if (!$user) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Authentication required.']);
            exit;
        }

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $subId = trim($body['subscription_id'] ?? '');
        if (!$subId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Subscription ID is required.']);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE subscriptions SET status = 'cancelled' WHERE id = ? AND user_id = ?");
        $stmt->execute([$subId, $user['id']]);
        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Subscription not found or already cancelled.']);
            exit;
        }

        echo json_encode(['success' => true, 'message' => 'Subscription cancelled successfully.']);
        exit;
    }

    // Unknown action
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Unknown action: ' . $action]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}
