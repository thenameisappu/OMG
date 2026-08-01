<?php
// API Endpoint for Surprise Experience Builder & Google Maps Pincode Validation
require_once 'config.php';

header('Content-Type: application/json');

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed"]);
    exit();
}

// Ensure database tables exist
ensureSurpriseTablesExist($db);

$action = $_GET['action'] ?? $_POST['action'] ?? 'get_data';

// 1. Fetch Dynamic Surprise Experiences & Upgrades
if ($action === 'get_data') {
    try {
        // Fetch active base experiences
        $expStmt = $db->query("SELECT * FROM `surprise_experiences` WHERE `is_active` = 1 ORDER BY `display_order` ASC, `id` ASC");
        $experiences = $expStmt->fetchAll(PDO::FETCH_ASSOC);

        // Format features JSON for frontend consumption
        foreach ($experiences as &$exp) {
            $exp['base_price'] = (float)$exp['base_price'];
            if (!empty($exp['features'])) {
                $decoded = json_decode($exp['features'], true);
                $exp['features'] = is_array($decoded) ? $decoded : array_map('trim', explode(',', $exp['features']));
            } else {
                $exp['features'] = [];
            }
        }
        unset($exp);

        // Fetch active upgrades
        $upgStmt = $db->query("SELECT * FROM `surprise_upgrades` WHERE `is_active` = 1 ORDER BY `display_order` ASC, `id` ASC");
        $upgrades = $upgStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($upgrades as &$upg) {
            $upg['price'] = (float)$upg['price'];
        }
        unset($upg);

        echo json_encode([
            "success" => true,
            "experiences" => $experiences,
            "upgrades" => $upgrades
        ]);
        exit();
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["error" => $e->getMessage()]);
        exit();
    }
}

// 2. Google Maps API-Based Pincode Validation (Bengaluru Delivery Area)
if ($action === 'check_pincode') {
    $pincode = trim($_GET['pincode'] ?? $_POST['pincode'] ?? '');

    // Requirement: Accept only exactly 6 numeric digits
    if (!preg_match('/^[0-9]{6}$/', $pincode)) {
        echo json_encode([
            "valid" => false,
            "message" => "Please enter a valid 6-digit numeric pincode."
        ]);
        exit();
    }

    $apiKey = getenv('GOOGLE_MAPS_API_KEY') ?: ($_ENV['GOOGLE_MAPS_API_KEY'] ?? ($_SERVER['GOOGLE_MAPS_API_KEY'] ?? ''));

    if (empty($apiKey) || $apiKey === 'YOUR_GOOGLE_MAPS_API_KEY') {
        // Fallback check if API key is not yet set in production
        // Bengaluru postal region (560xxx)
        if (strpos($pincode, '560') === 0) {
            echo json_encode([
                "valid" => true,
                "message" => "Delivery Available",
                "pincode" => $pincode,
                "area_name" => "Bengaluru Region"
            ]);
        } else {
            echo json_encode([
                "valid" => false,
                "message" => "Sorry, we currently deliver only within Bengaluru."
            ]);
        }
        exit();
    }

    // Call Google Maps Geocoding API securely from backend
    $url = "https://maps.googleapis.com/maps/api/geocode/json?components=postal_code:" . urlencode($pincode) . "|country:IN&key=" . urlencode($apiKey);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 6);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($response === false || !empty($curlErr)) {
        echo json_encode([
            "valid" => false,
            "error_type" => "service_unavailable",
            "message" => "Pincode validation service is temporarily unavailable. Please try again shortly."
        ]);
        exit();
    }

    $data = json_decode($response, true);

    if (!$data || !isset($data['status'])) {
        echo json_encode([
            "valid" => false,
            "error_type" => "service_unavailable",
            "message" => "Google Maps service is temporarily unavailable. Please try again shortly."
        ]);
        exit();
    }

    if ($data['status'] === 'REQUEST_DENIED' || $data['status'] === 'OVER_QUERY_LIMIT') {
        // Fallback for API Key quota or permission issues
        if (strpos($pincode, '560') === 0) {
            echo json_encode([
                "valid" => true,
                "message" => "Delivery Available",
                "pincode" => $pincode,
                "area_name" => "Bengaluru Region"
            ]);
        } else {
            echo json_encode([
                "valid" => false,
                "message" => "Sorry, we currently deliver only within Bengaluru."
            ]);
        }
        exit();
    }

    if ($data['status'] === 'ZERO_RESULTS' || empty($data['results'])) {
        echo json_encode([
            "valid" => false,
            "message" => "Sorry, we currently deliver only within Bengaluru."
        ]);
        exit();
    }

    if ($data['status'] === 'OK' && !empty($data['results'])) {
        $isBengaluru = false;
        $areaName = "";

        $formattedAddress = $data['results'][0]['formatted_address'] ?? '';
        if (preg_match('/bengaluru|bangalore/i', $formattedAddress)) {
            $isBengaluru = true;
        }

        if (isset($data['results'][0]['address_components'])) {
            foreach ($data['results'][0]['address_components'] as $component) {
                $name = $component['long_name'] ?? '';
                if (preg_match('/bengaluru|bangalore/i', $name)) {
                    $isBengaluru = true;
                }
                if (array_intersect(['sublocality', 'sublocality_level_1', 'locality', 'neighborhood'], $component['types'])) {
                    if (empty($areaName) && !preg_match('/bengaluru|bangalore/i', $name)) {
                        $areaName = $name;
                    }
                }
            }
        }

        if ($isBengaluru) {
            $locationDetail = !empty($areaName) ? " ({$areaName})" : "";
            echo json_encode([
                "valid" => true,
                "message" => "Delivery Available" . $locationDetail,
                "pincode" => $pincode,
                "area_name" => $areaName
            ]);
        } else {
            echo json_encode([
                "valid" => false,
                "message" => "Sorry, we currently deliver only within Bengaluru."
            ]);
        }
        exit();
    }

    echo json_encode([
        "valid" => false,
        "message" => "Sorry, we currently deliver only within Bengaluru."
    ]);
    exit();
}

// Default fallback response
echo json_encode(["error" => "Invalid action requested"]);
?>
