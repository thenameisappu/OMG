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

$data = json_decode(file_get_contents("php://input"));
$action = isset($_GET['action']) ? $_GET['action'] : '';

$userId = authenticate(); // Now using centralized function from config.php

switch ($action) {
    case 'get_profile':
        getProfile($db, $userId);
        break;
    case 'update_profile':
        updateProfile($db, $userId, $data);
        break;
    default:
        echo json_encode(["message" => "Invalid action"]);
        break;
}

function getProfile($db, $userId)
{
    $query = "SELECT * FROM user_profiles WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":id", $userId);
    $stmt->execute();

    $profile = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode($profile);
}

function updateProfile($db, $userId, $data)
{
    // Check if profile exists
    $checkQuery = "SELECT id FROM user_profiles WHERE id = :id";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->bindParam(":id", $userId);
    $checkStmt->execute();

    if ($checkStmt->rowCount() == 0) {
        // Insert new profile
        $query = "INSERT INTO user_profiles (id, name, phone, address, city) VALUES (:id, :name, :phone, :address, :city)";
        $stmt = $db->prepare($query);

        $name = isset($data->name) ? $data->name : null;
        $phone = isset($data->phone) ? $data->phone : null;
        $address = isset($data->address) ? $data->address : null;
        $city = isset($data->city) ? $data->city : null;

        $stmt->bindParam(":id", $userId);
        $stmt->bindParam(":name", $name);
        $stmt->bindParam(":phone", $phone);
        $stmt->bindParam(":address", $address);
        $stmt->bindParam(":city", $city);
    }
    else {
        // Update existing profile - only update provided fields
        $fieldsToUpdate = [];
        $params = [":id" => $userId];

        if (isset($data->name)) {
            $fieldsToUpdate[] = "name = :name";
            $params[":name"] = $data->name;
        }
        if (isset($data->phone)) {
            $fieldsToUpdate[] = "phone = :phone";
            $params[":phone"] = $data->phone;
        }
        if (isset($data->address)) {
            $fieldsToUpdate[] = "address = :address";
            $params[":address"] = $data->address;
        }
        if (isset($data->city)) {
            $fieldsToUpdate[] = "city = :city";
            $params[":city"] = $data->city;
        }

        if (empty($fieldsToUpdate)) {
            // Nothing to update
            echo json_encode(["message" => "No changes provided."]);
            return;
        }

        $query = "UPDATE user_profiles SET " . implode(", ", $fieldsToUpdate) . " WHERE id = :id";
        $stmt = $db->prepare($query);

        foreach ($params as $key => &$val) {
            $stmt->bindParam($key, $val);
        }
    }

    if ($stmt->execute()) {
        getProfile($db, $userId);
    }
    else {
        http_response_code(500);
        echo json_encode(["message" => "Unable to update profile."]);
    }
}
