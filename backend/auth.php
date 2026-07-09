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

if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
}
$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'register':
        register($db, $data);
        break;
    case 'login':
        login($db, $data);
        break;
    case 'logout':
        logout();
        break;
    case 'get_user':
        getUser($db);
        break;
    case 'verify_otp':
        verifyOtp($db, $data);
        break;
    case 'resend_otp':
        resendOtp($db, $data);
        break;
    case 'forgot_password':
        forgotPassword($db, $data);
        break;
    case 'verify_reset_otp':
        verifyResetOtp($db, $data);
        break;
    case 'reset_password':
        resetPassword($db, $data);
        break;
    default:
        echo json_encode(["message" => "Invalid action"]);
        break;
}

function sendOTPEmail($email, $otp)
{
    $subject = "Verify your OMG Account";
    $message = "Your verification code is: " . $otp . "\n\nThis code will expire in 15 minutes.";
    $headers = "From: krappu203@gmail.com";

    // In a real production environment, you'd use a robust mailer like PHPMailer or an API
    return mail($email, $subject, $message, $headers);
}

function register($db, $data)
{
    if (!empty($data->email) && !empty($data->password)) {
        // Check if user exists
        $query = "SELECT id FROM users WHERE email = :email";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":email", $data->email);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            http_response_code(400);
            echo json_encode(["message" => "User already exists."]);
            return;
        }

        $query = "INSERT INTO users (id, email, password_hash, otp_code, otp_expiry) VALUES (:id, :email, :password_hash, :otp_code, :otp_expiry)";
        $stmt = $db->prepare($query);

        // Generate UUID
        $uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );

        $password_hash = password_hash($data->password, PASSWORD_BCRYPT);
        $otp = sprintf("%06d", mt_rand(0, 999999));
        $expiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));

        $stmt->bindParam(":id", $uuid);
        $stmt->bindParam(":email", $data->email);
        $stmt->bindParam(":password_hash", $password_hash);
        $stmt->bindParam(":otp_code", $otp);
        $stmt->bindParam(":otp_expiry", $expiry);

        if ($stmt->execute()) {
            // Create empty profile
            $profileQuery = "INSERT INTO user_profiles (id) VALUES (:id)";
            $profileStmt = $db->prepare($profileQuery);
            $profileStmt->bindParam(":id", $uuid);
            $profileStmt->execute();

            sendOTPEmail($data->email, $otp);

            http_response_code(201);
            echo json_encode([
                "message" => "User was created. Please verify your email.",
                "requires_verification" => true,
                "email" => $data->email
            ]);
        }
        else {
            http_response_code(503);
            echo json_encode(["message" => "Unable to create user."]);
        }
    }
    else {
        http_response_code(400);
        echo json_encode(["message" => "Incomplete data."]);
    }
}

function login($db, $data)
{
    if (!empty($data->email) && !empty($data->password)) {
        $query = "SELECT id, email, password_hash, is_verified FROM users WHERE email = :email";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":email", $data->email);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (password_verify($data->password, $row['password_hash'])) {
                if ($row['is_verified'] == 0) {
                    http_response_code(403);
                    echo json_encode([
                        "message" => "Email not verified.",
                        "requires_verification" => true,
                        "email" => $row['email']
                    ]);
                    return;
                }

                // Set session
                $_SESSION['user_id'] = $row['id'];

                http_response_code(200);
                echo json_encode([
                    "message" => "Login successful.",
                    "user" => [
                        "id" => $row['id'],
                        "email" => $row['email']
                    ]
                ]);
            }
            else {
                http_response_code(401);
                echo json_encode(["message" => "Invalid credentials."]);
            }
        }
        else {
            http_response_code(401);
            echo json_encode(["message" => "Invalid credentials."]);
        }
    }
    else {
        http_response_code(400);
        echo json_encode(["message" => "Incomplete data."]);
    }
}

function verifyOtp($db, $data)
{
    if (!empty($data->email) && !empty($data->otp)) {
        $query = "SELECT id, otp_code, otp_expiry FROM users WHERE email = :email";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":email", $data->email);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $now = date('Y-m-d H:i:s');

            if ($row['otp_code'] === $data->otp && $row['otp_expiry'] >= $now) {
                $updateQuery = "UPDATE users SET is_verified = 1, otp_code = NULL, otp_expiry = NULL WHERE id = :id";
                $updateStmt = $db->prepare($updateQuery);
                $updateStmt->bindParam(":id", $row['id']);

                if ($updateStmt->execute()) {
                    // Automatically log them in
                    $_SESSION['user_id'] = $row['id'];
                    echo json_encode([
                        "message" => "Email verified successfully.",
                        "user" => ["id" => $row['id'], "email" => $data->email]
                    ]);
                }
                else {
                    http_response_code(500);
                    echo json_encode(["message" => "Failed to update verification status."]);
                }
            }
            else {
                http_response_code(400);
                echo json_encode(["message" => "Invalid or expired OTP."]);
            }
        }
        else {
            http_response_code(404);
            echo json_encode(["message" => "User not found."]);
        }
    }
    else {
        http_response_code(400);
        echo json_encode(["message" => "Incomplete data."]);
    }
}

function resendOtp($db, $data)
{
    if (!empty($data->email)) {
        $otp = sprintf("%06d", mt_rand(0, 999999));
        $expiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));

        $query = "UPDATE users SET otp_code = :otp_code, otp_expiry = :otp_expiry WHERE email = :email";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":otp_code", $otp);
        $stmt->bindParam(":otp_expiry", $expiry);
        $stmt->bindParam(":email", $data->email);

        if ($stmt->execute() && $stmt->rowCount() > 0) {
            sendOTPEmail($data->email, $otp);
            echo json_encode(["message" => "OTP resent successfully."]);
        }
        else {
            http_response_code(404);
            echo json_encode(["message" => "User not found."]);
        }
    }
    else {
        http_response_code(400);
        echo json_encode(["message" => "Incomplete data."]);
    }
}

function sendResetOTPEmail($email, $otp)
{
    $subject = "Reset your OMG Account Password";
    $message = "Your password reset verification code is: " . $otp . "\n\nThis code will expire in 15 minutes.";
    $headers = "From: krappu203@gmail.com";

    return mail($email, $subject, $message, $headers);
}

function forgotPassword($db, $data)
{
    if (!empty($data->email)) {
        // Cooldown check (60 seconds)
        if (isset($_SESSION['last_otp_sent']) && (time() - $_SESSION['last_otp_sent']) < 60) {
            http_response_code(429);
            echo json_encode(["message" => "Please wait 60 seconds before requesting another code."]);
            return;
        }

        // Verify user exists and is verified
        $query = "SELECT id, is_verified FROM users WHERE email = :email";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":email", $data->email);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row['is_verified'] == 0) {
                http_response_code(400);
                echo json_encode(["message" => "Account is not verified. Please register first or verify your email."]);
                return;
            }

            $otp = sprintf("%06d", mt_rand(0, 999999));
            $expiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));

            $updateQuery = "UPDATE users SET otp_code = :otp_code, otp_expiry = :otp_expiry WHERE email = :email";
            $updateStmt = $db->prepare($updateQuery);
            $updateStmt->bindParam(":otp_code", $otp);
            $updateStmt->bindParam(":otp_expiry", $expiry);
            $updateStmt->bindParam(":email", $data->email);

            if ($updateStmt->execute()) {
                sendResetOTPEmail($data->email, $otp);
                $_SESSION['last_otp_sent'] = time();
                $_SESSION['reset_email'] = $data->email;
                $_SESSION['reset_verified'] = false;
                $_SESSION['reset_otp_attempts'] = 0;

                echo json_encode(["message" => "OTP sent successfully."]);
            }
            else {
                http_response_code(500);
                echo json_encode(["message" => "Failed to generate reset code."]);
            }
        }
        else {
            http_response_code(404);
            echo json_encode(["message" => "User not found."]);
        }
    }
    else {
        http_response_code(400);
        echo json_encode(["message" => "Incomplete data."]);
    }
}

function verifyResetOtp($db, $data)
{
    if (!empty($data->email) && !empty($data->otp)) {
        if (!isset($_SESSION['reset_email']) || $_SESSION['reset_email'] !== $data->email) {
            http_response_code(400);
            echo json_encode(["message" => "Session mismatch. Please request a new code."]);
            return;
        }

        // Brute-force protection: check attempts
        if (isset($_SESSION['reset_otp_attempts']) && $_SESSION['reset_otp_attempts'] >= 5) {
            // Invalidate OTP in DB
            $query = "UPDATE users SET otp_code = NULL, otp_expiry = NULL WHERE email = :email";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":email", $data->email);
            $stmt->execute();

            unset($_SESSION['reset_email']);
            unset($_SESSION['reset_verified']);
            unset($_SESSION['reset_otp_attempts']);

            http_response_code(429);
            echo json_encode(["message" => "Too many failed attempts. Please request a new verification code."]);
            return;
        }

        $query = "SELECT otp_code, otp_expiry FROM users WHERE email = :email";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":email", $data->email);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $now = date('Y-m-d H:i:s');

            if ($row['otp_code'] === $data->otp && $row['otp_expiry'] >= $now) {
                $_SESSION['reset_verified'] = true;
                echo json_encode(["message" => "OTP verified successfully."]);
            }
            else {
                if (!isset($_SESSION['reset_otp_attempts'])) {
                    $_SESSION['reset_otp_attempts'] = 0;
                }
                $_SESSION['reset_otp_attempts']++;

                http_response_code(400);
                echo json_encode(["message" => "Invalid or expired OTP."]);
            }
        }
        else {
            http_response_code(404);
            echo json_encode(["message" => "User not found."]);
        }
    }
    else {
        http_response_code(400);
        echo json_encode(["message" => "Incomplete data."]);
    }
}

function resetPassword($db, $data)
{
    if (!empty($data->email) && !empty($data->otp) && !empty($data->password)) {
        if (!isset($_SESSION['reset_verified']) || $_SESSION['reset_verified'] !== true || !isset($_SESSION['reset_email']) || $_SESSION['reset_email'] !== $data->email) {
            http_response_code(403);
            echo json_encode(["message" => "Action forbidden. Please verify your OTP first."]);
            return;
        }

        if (strlen($data->password) < 6) {
            http_response_code(400);
            echo json_encode(["message" => "Password must be at least 6 characters."]);
            return;
        }

        // Final verification that OTP in DB matches to prevent replay/abuse
        $query = "SELECT id, otp_code, otp_expiry FROM users WHERE email = :email";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":email", $data->email);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $now = date('Y-m-d H:i:s');

            if ($row['otp_code'] === $data->otp && $row['otp_expiry'] >= $now) {
                $password_hash = password_hash($data->password, PASSWORD_BCRYPT);

                $updateQuery = "UPDATE users SET password_hash = :password_hash, otp_code = NULL, otp_expiry = NULL WHERE id = :id";
                $updateStmt = $db->prepare($updateQuery);
                $updateStmt->bindParam(":password_hash", $password_hash);
                $updateStmt->bindParam(":id", $row['id']);

                if ($updateStmt->execute()) {
                    // Clear reset session variables
                    unset($_SESSION['reset_email']);
                    unset($_SESSION['reset_verified']);
                    unset($_SESSION['reset_otp_attempts']);

                    echo json_encode(["message" => "Password reset successfully."]);
                }
                else {
                    http_response_code(500);
                    echo json_encode(["message" => "Failed to reset password."]);
                }
            }
            else {
                http_response_code(400);
                echo json_encode(["message" => "Invalid or expired OTP."]);
            }
        }
        else {
            http_response_code(404);
            echo json_encode(["message" => "User not found."]);
        }
    }
    else {
        http_response_code(400);
        echo json_encode(["message" => "Incomplete data."]);
    }
}

function logout()
{
    session_unset();
    session_destroy();
    echo json_encode(["message" => "Logged out successfully"]);
}

function getUser($db)
{
    if (isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];

        // Fetch user
        $query = "SELECT id, email FROM users WHERE id = :id AND is_verified = 1";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":id", $userId);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(["user" => $user]);
            return;
        }
    }

    // Check for "remember me" cookie or similar if implemented, otherwise 401
    // For now, strict session check

    http_response_code(401);
    echo json_encode(["message" => "Unauthorized"]);
}
