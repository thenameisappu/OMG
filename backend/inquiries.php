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

        $email = trim($data['email'] ?? '');
        $name = trim($data['name'] ?? '');
        if (!$name && $email) {
            $name = explode('@', $email)[0];
        }
        if (!$name) {
            $name = 'Valued Customer';
        }

        $contact_no = trim($data['contactNo'] ?? '');
        $event_type = trim($data['eventType'] ?? 'Surprise Event');
        $service_name = trim($data['serviceName'] ?? '');
        $address = trim($data['address'] ?? '');
        $city = trim($data['city'] ?? '');
        $message = trim($data['message'] ?? '');

        if (!$email || !$contact_no || !$message) {
            http_response_code(400);
            echo json_encode(['error' => 'Please fill in your email, contact number, and message.']);
            exit();
        }

        $stmt = $db->prepare(
            "INSERT INTO inquiries (name, email, contact_no, event_type, service_name, address, city, message)
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
            echo json_encode(['success' => true, 'message' => 'Inquiry saved successfully.']);
        }
        else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to save inquiry.']);
        }
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $db->query("SELECT * FROM inquiries ORDER BY created_at DESC");
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
