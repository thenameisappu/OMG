<?php
require_once 'config.php';
header('Content-Type: application/json');

try {
    $database = new Database();
    $db = $database->getConnection();

    if (!$db) {
        http_response_code(500);
        echo json_encode(['error' => 'Database connection failed']);
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);

        $name = trim($data['name'] ?? '');
        $email = trim($data['email'] ?? '');
        $contact_no = trim($data['contactNo'] ?? '');
        $event_type = trim($data['eventType'] ?? '');
        $service_name = trim($data['serviceName'] ?? '');
        $address = trim($data['address'] ?? '');
        $city = trim($data['city'] ?? '');
        $message = trim($data['message'] ?? '');

        if (!$name || !$email || !$contact_no || !$event_type || !$message) {
            http_response_code(400);
            echo json_encode(['error' => 'All required fields must be filled.']);
            exit();
        }

        $stmt = $db->prepare(
            "INSERT INTO customisations (name, email, contact_no, event_type, service_name, address, city, message)
             VALUES (:name, :email, :contact_no, :event_type, :service_name, :address, :city, :message)"
        );
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':contact_no', $contact_no);
        $stmt->bindParam(':event_type', $event_type);
        $stmt->bindValue(':service_name', $service_name ?: null, PDO::PARAM_STR);
        $stmt->bindValue(':address', $address ?: null, PDO::PARAM_STR);
        $stmt->bindValue(':city', $city ?: null, PDO::PARAM_STR);
        $stmt->bindParam(':message', $message);

        if ($stmt->execute()) {
            http_response_code(201);
            echo json_encode(['success' => true, 'message' => 'Customisation request saved successfully.']);
        }
        else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to save customisation request.']);
        }
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $db->query("SELECT * FROM customisations ORDER BY created_at DESC");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit();
    }

    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);

}
catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
