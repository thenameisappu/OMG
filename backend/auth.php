<?php
require_once 'config.php';
require_once 'mailer.php';

header("Content-Type: application/json; charset=UTF-8");

$database = new Database();
$db = $database->getConnection();

if ($db === null) {
    http_response_code(500);
    echo json_encode(["message" => "Database connection failed."]);
    exit();
}

// Ensure schema is updated with necessary auth & OTP columns
ensureAuthTablesExist($db);

$data = json_decode(file_get_contents("php://input"));
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
        http_response_code(400);
        echo json_encode(["message" => "Invalid action."]);
        break;
}

// ── 1. USER REGISTRATION (WITH OTP EMAIL VERIFICATION) ────────────────────
function register($db, $data)
{
    $email = strtolower(trim($data->email ?? ''));
    $password = trim($data->password ?? '');
    $name = trim($data->name ?? '');

    if (empty($email) || empty($password)) {
        http_response_code(400);
        echo json_encode(["message" => "Email and password are required."]);
        return;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(["message" => "Please enter a valid email address."]);
        return;
    }

    if (strlen($password) < 6) {
        http_response_code(400);
        echo json_encode(["message" => "Password must be at least 6 characters long."]);
        return;
    }

    // Check if user already exists
    $query = "SELECT id, is_verified FROM users WHERE email = :email";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":email", $email);
    $stmt->execute();
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        if ((int)$existing['is_verified'] === 1) {
            http_response_code(400);
            echo json_encode(["message" => "User with this email already exists. Please log in."]);
            return;
        } else {
            // Update unverified user with new password & fresh OTP
            $uuid = $existing['id'];
            $password_hash = password_hash($password, PASSWORD_BCRYPT);
            $otp = sprintf("%06d", mt_rand(0, 999999));
            $expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));

            $update = $db->prepare("UPDATE users SET password_hash = :hash, otp_code = :otp, otp_expiry = :expiry WHERE id = :id");
            $update->execute([':hash' => $password_hash, ':otp' => $otp, ':expiry' => $expiry, ':id' => $uuid]);

            if (!empty($name)) {
                $profUp = $db->prepare("INSERT INTO user_profiles (id, name) VALUES (:id, :name) ON DUPLICATE KEY UPDATE name = :name2");
                $profUp->execute([':id' => $uuid, ':name' => $name, ':name2' => $name]);
            }

            $userName = !empty($name) ? $name : explode('@', $email)[0];
            $htmlBody = buildEmailVerificationTemplate($userName, $otp);
            sendEmail($email, "Verify your OH MY GUDNESS Account", $htmlBody);

            http_response_code(200);
            echo json_encode([
                "success" => true,
                "message" => "A new 6-digit verification code has been sent to your email.",
                "requires_verification" => true,
                "email" => $email
            ]);
            return;
        }
    }

    // Create new unverified user
    $uuid = sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );

    $password_hash = password_hash($password, PASSWORD_BCRYPT);
    $otp = sprintf("%06d", mt_rand(0, 999999));
    $expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));

    $stmt = $db->prepare("INSERT INTO users (id, email, password_hash, is_verified, otp_code, otp_expiry) VALUES (:id, :email, :password_hash, 0, :otp_code, :otp_expiry)");
    $stmt->bindParam(":id", $uuid);
    $stmt->bindParam(":email", $email);
    $stmt->bindParam(":password_hash", $password_hash);
    $stmt->bindParam(":otp_code", $otp);
    $stmt->bindParam(":otp_expiry", $expiry);

    if ($stmt->execute()) {
        // Create user profile with name if provided
        $profileStmt = $db->prepare("INSERT INTO user_profiles (id, name) VALUES (:id, :name)");
        $profileStmt->bindParam(":id", $uuid);
        $profileStmt->bindParam(":name", $name);
        $profileStmt->execute();

        // Send OTP Email
        $userName = !empty($name) ? $name : explode('@', $email)[0];
        $htmlBody = buildEmailVerificationTemplate($userName, $otp);
        $mailSent = sendEmail($email, "Verify your OH MY GUDNESS Account", $htmlBody);

        http_response_code(201);
        echo json_encode([
            "success" => true,
            "message" => "Account created! A 6-digit verification code has been sent to your email.",
            "requires_verification" => true,
            "email" => $email
        ]);
    } else {
        http_response_code(500);
        echo json_encode(["message" => "Unable to create user account. Please try again."]);
    }
}

// ── 2. USER LOGIN ──────────────────────────────────────────────────────────
function login($db, $data)
{
    $email = strtolower(trim($data->email ?? ''));
    $password = trim($data->password ?? '');

    if (empty($email) || empty($password)) {
        http_response_code(400);
        echo json_encode(["message" => "Email and password are required."]);
        return;
    }

    $query = "SELECT id, email, password_hash, is_verified FROM users WHERE email = :email";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":email", $email);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (password_verify($password, $row['password_hash'])) {
            if ((int)$row['is_verified'] === 0) {
                // Generate fresh OTP for unverified user
                $otp = sprintf("%06d", mt_rand(0, 999999));
                $expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));
                $up = $db->prepare("UPDATE users SET otp_code = :otp, otp_expiry = :expiry WHERE id = :id");
                $up->execute([':otp' => $otp, ':expiry' => $expiry, ':id' => $row['id']]);

                $userName = explode('@', $email)[0];
                $htmlBody = buildEmailVerificationTemplate($userName, $otp);
                sendEmail($email, "Verify your OH MY GUDNESS Account", $htmlBody);

                http_response_code(403);
                echo json_encode([
                    "message" => "Account not verified. A verification code has been sent to your email.",
                    "requires_verification" => true,
                    "email" => $email
                ]);
                return;
            }

            // Generate new session token for Single Active Login enforcement
            $sessionToken = bin2hex(random_bytes(32));

            // Save active session token to database
            $sessionUp = $db->prepare("UPDATE users SET current_session_id = :st WHERE id = :id");
            $sessionUp->execute([':st' => $sessionToken, ':id' => $row['id']]);

            // Regenerate Session & Set Login
            session_regenerate_id(true);
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['session_token'] = $sessionToken;
            $_SESSION['user_last_activity'] = time();

            // Fetch user profile data
            $profStmt = $db->prepare("SELECT name, phone, address, city FROM user_profiles WHERE id = :id LIMIT 1");
            $profStmt->execute([':id' => $row['id']]);
            $profile = $profStmt->fetch(PDO::FETCH_ASSOC) ?: [];

            http_response_code(200);
            echo json_encode([
                "message" => "Login successful.",
                "token" => $sessionToken,
                "user" => [
                    "id" => $row['id'],
                    "email" => $row['email'],
                    "name" => $profile['name'] ?? null,
                    "phone" => $profile['phone'] ?? null,
                    "address" => $profile['address'] ?? null,
                    "city" => $profile['city'] ?? null
                ]
            ]);
        } else {
            http_response_code(401);
            echo json_encode(["message" => "Invalid email or password."]);
        }
    } else {
        http_response_code(401);
        echo json_encode(["message" => "Invalid email or password."]);
    }
}

// ── 3. VERIFY REGISTRATION OTP ──────────────────────────────────────────────
function verifyOtp($db, $data)
{
    $email = strtolower(trim($data->email ?? ''));
    $otp = trim($data->otp ?? '');

    if (empty($email) || empty($otp)) {
        http_response_code(400);
        echo json_encode(["message" => "Email and OTP code are required."]);
        return;
    }

    $query = "SELECT id, otp_code, otp_expiry FROM users WHERE email = :email";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":email", $email);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $now = date('Y-m-d H:i:s');

        if ($row['otp_code'] === $otp && $row['otp_expiry'] >= $now) {
            $sessionToken = bin2hex(random_bytes(32));

            $updateQuery = "UPDATE users SET is_verified = 1, current_session_id = :st, otp_code = NULL, otp_expiry = NULL WHERE id = :id";
            $updateStmt = $db->prepare($updateQuery);
            $updateStmt->bindParam(":st", $sessionToken);
            $updateStmt->bindParam(":id", $row['id']);

            if ($updateStmt->execute()) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['session_token'] = $sessionToken;
                $_SESSION['user_last_activity'] = time();

                // Send Welcome Email
                $userName = explode('@', $email)[0];
                $welcomeHtml = buildWelcomeEmailTemplate($userName);
                sendEmail($email, "Welcome to OH MY GUDNESS!", $welcomeHtml);

                // Fetch user profile
                $profStmt = $db->prepare("SELECT name, phone, address, city FROM user_profiles WHERE id = :id LIMIT 1");
                $profStmt->execute([':id' => $row['id']]);
                $profile = $profStmt->fetch(PDO::FETCH_ASSOC) ?: [];

                echo json_encode([
                    "message" => "Email verified successfully! Welcome to OH MY GUDNESS.",
                    "token" => $sessionToken,
                    "user" => [
                        "id" => $row['id'],
                        "email" => $email,
                        "name" => $profile['name'] ?? null,
                        "phone" => $profile['phone'] ?? null,
                        "address" => $profile['address'] ?? null,
                        "city" => $profile['city'] ?? null
                    ]
                ]);
            } else {
                http_response_code(500);
                echo json_encode(["message" => "Failed to update verification status."]);
            }
        } else {
            http_response_code(400);
            echo json_encode(["message" => "Invalid or expired OTP code. Please try again."]);
        }
    } else {
        http_response_code(404);
        echo json_encode(["message" => "User account not found."]);
    }
}

// ── 4. RESEND OTP (WITH 60-SECOND COOLDOWN) ────────────────────────────────
function resendOtp($db, $data)
{
    $email = strtolower(trim($data->email ?? ''));

    if (empty($email)) {
        http_response_code(400);
        echo json_encode(["message" => "Email is required."]);
        return;
    }

    $cooldownKey = 'last_resend_' . md5($email);
    if (isset($_SESSION[$cooldownKey]) && (time() - $_SESSION[$cooldownKey]) < 60) {
        $remaining = 60 - (time() - $_SESSION[$cooldownKey]);
        http_response_code(429);
        echo json_encode(["message" => "Please wait {$remaining} seconds before requesting another code."]);
        return;
    }

    $otp = sprintf("%06d", mt_rand(0, 999999));
    $expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));

    $query = "UPDATE users SET otp_code = :otp_code, otp_expiry = :otp_expiry WHERE email = :email";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":otp_code", $otp);
    $stmt->bindParam(":otp_expiry", $expiry);
    $stmt->bindParam(":email", $email);

    if ($stmt->execute() && $stmt->rowCount() > 0) {
        $_SESSION[$cooldownKey] = time();
        $userName = explode('@', $email)[0];
        $htmlBody = buildEmailVerificationTemplate($userName, $otp);
        sendEmail($email, "Verify your OH MY GUDNESS Account", $htmlBody);
        echo json_encode(["message" => "A new 6-digit OTP code has been sent to your email."]);
    } else {
        http_response_code(404);
        echo json_encode(["message" => "User account not found."]);
    }
}

// ── 5. USER FORGOT PASSWORD REQUEST ─────────────────────────────────────────
function forgotPassword($db, $data)
{
    $email = strtolower(trim($data->email ?? ''));

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(["message" => "Please provide a valid registered email address."]);
        return;
    }

    // Cooldown check (60 seconds)
    $cooldownKey = 'last_reset_otp_' . md5($email);
    if (isset($_SESSION[$cooldownKey]) && (time() - $_SESSION[$cooldownKey]) < 60) {
        $remaining = 60 - (time() - $_SESSION[$cooldownKey]);
        http_response_code(429);
        echo json_encode(["message" => "Please wait {$remaining} seconds before requesting another code."]);
        return;
    }

    // Verify active user exists
    $query = "SELECT id, is_verified FROM users WHERE email = :email";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":email", $email);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $otp = sprintf("%06d", mt_rand(0, 999999));
        $expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));

        $updateQuery = "UPDATE users SET otp_code = :otp_code, otp_expiry = :otp_expiry WHERE email = :email";
        $updateStmt = $db->prepare($updateQuery);
        $updateStmt->bindParam(":otp_code", $otp);
        $updateStmt->bindParam(":otp_expiry", $expiry);
        $updateStmt->bindParam(":email", $email);

        if ($updateStmt->execute()) {
            $_SESSION[$cooldownKey] = time();
            $_SESSION['reset_email'] = $email;
            $_SESSION['reset_verified'] = false;
            $_SESSION['reset_otp_attempts'] = 0;

            $userName = explode('@', $email)[0];
            $htmlBody = buildForgotPasswordTemplate($userName, $otp);
            sendEmail($email, "Reset your OH MY GUDNESS Password", $htmlBody);

            echo json_encode(["message" => "A 6-digit password reset code has been sent to your email."]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "Failed to generate password reset code."]);
        }
    } else {
        http_response_code(404);
        echo json_encode(["message" => "No account found matching this email address."]);
    }
}

// ── 6. VERIFY RESET OTP ────────────────────────────────────────────────────
function verifyResetOtp($db, $data)
{
    $email = strtolower(trim($data->email ?? ''));
    $otp = trim($data->otp ?? '');

    if (empty($email) || empty($otp)) {
        http_response_code(400);
        echo json_encode(["message" => "Email and OTP code are required."]);
        return;
    }

    // Brute-force protection: check attempts
    if (isset($_SESSION['reset_otp_attempts']) && $_SESSION['reset_otp_attempts'] >= 5) {
        $query = "UPDATE users SET otp_code = NULL, otp_expiry = NULL WHERE email = :email";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":email", $email);
        $stmt->execute();

        unset($_SESSION['reset_email']);
        unset($_SESSION['reset_verified']);
        unset($_SESSION['reset_otp_attempts']);

        http_response_code(429);
        echo json_encode(["message" => "Too many failed attempts. Please request a new reset code."]);
        return;
    }

    $query = "SELECT otp_code, otp_expiry FROM users WHERE email = :email";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":email", $email);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $now = date('Y-m-d H:i:s');

        if ($row['otp_code'] === $otp && $row['otp_expiry'] >= $now) {
            $_SESSION['reset_verified'] = true;
            $_SESSION['reset_email'] = $email;
            echo json_encode(["message" => "OTP verified successfully. Please enter your new password."]);
        } else {
            if (!isset($_SESSION['reset_otp_attempts'])) {
                $_SESSION['reset_otp_attempts'] = 0;
            }
            $_SESSION['reset_otp_attempts']++;

            http_response_code(400);
            echo json_encode(["message" => "Invalid or expired OTP code."]);
        }
    } else {
        http_response_code(404);
        echo json_encode(["message" => "User account not found."]);
    }
}

// ── 7. RESET PASSWORD ───────────────────────────────────────────────────────
function resetPassword($db, $data)
{
    $email = strtolower(trim($data->email ?? ''));
    $otp = trim($data->otp ?? '');
    $password = trim($data->password ?? '');

    if (empty($email) || empty($otp) || empty($password)) {
        http_response_code(400);
        echo json_encode(["message" => "Email, OTP code, and new password are required."]);
        return;
    }

    if (strlen($password) < 6) {
        http_response_code(400);
        echo json_encode(["message" => "Password must be at least 6 characters long."]);
        return;
    }

    // Verify OTP in DB for security
    $query = "SELECT id, otp_code, otp_expiry FROM users WHERE email = :email";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":email", $email);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $now = date('Y-m-d H:i:s');

        if ($row['otp_code'] === $otp && $row['otp_expiry'] >= $now) {
            $password_hash = password_hash($password, PASSWORD_BCRYPT);

            $updateQuery = "UPDATE users SET password_hash = :password_hash, is_verified = 1, otp_code = NULL, otp_expiry = NULL WHERE id = :id";
            $updateStmt = $db->prepare($updateQuery);
            $updateStmt->bindParam(":password_hash", $password_hash);
            $updateStmt->bindParam(":id", $row['id']);

            if ($updateStmt->execute()) {
                unset($_SESSION['reset_email']);
                unset($_SESSION['reset_verified']);
                unset($_SESSION['reset_otp_attempts']);

                echo json_encode(["message" => "Password reset successfully! You can now log in with your new password."]);
            } else {
                http_response_code(500);
                echo json_encode(["message" => "Failed to update password. Please try again."]);
            }
        } else {
            http_response_code(400);
            echo json_encode(["message" => "Invalid or expired OTP code."]);
        }
    } else {
        http_response_code(404);
        echo json_encode(["message" => "User account not found."]);
    }
}

function logout()
{
    global $db;
    if (isset($_SESSION['user_id']) && $db) {
        try {
            $up = $db->prepare("UPDATE users SET current_session_id = NULL WHERE id = :id");
            $up->execute([':id' => $_SESSION['user_id']]);
        } catch (Exception $e) {}
    }
    $_SESSION = array();
    @session_unset();
    @session_destroy();
    echo json_encode(["message" => "Logged out successfully"]);
}

function getUser($db)
{
    $userId = authenticate(); // Validates session/Bearer token + single active session
    if ($userId) {
        $query = "SELECT u.id, u.email, p.name, p.phone, p.address, p.city 
                  FROM users u 
                  LEFT JOIN user_profiles p ON u.id = p.id 
                  WHERE u.id = :id AND u.is_verified = 1";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":id", $userId);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(["user" => $user]);
            return;
        }
    }

    http_response_code(401);
    echo json_encode(["message" => "Unauthorized"]);
}
