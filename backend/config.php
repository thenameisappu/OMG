<?php

date_default_timezone_set('Asia/Kolkata');

// Start session for all requests
session_set_cookie_params([
    'samesite' => 'None',
    'secure' => true, // Required for SameSite=None
    'httponly' => true,
    'lifetime' => 86400 * 30 // 30 days
]);
session_start();

// Handle CORS dynamically
$allowedOrigins = [
    'http://localhost:5173',
    'http://localhost:3000',
    'http://localhost:8000',
    'https://mediumspringgreen-loris-371923.hostingersite.com',
    // Add other allowed origins here
];

$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';

if (in_array($origin, $allowedOrigins)) {
    header("Access-Control-Allow-Origin: " . $origin);
    header("Access-Control-Allow-Credentials: true");
}

header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

class Database
{
    private $host = 'localhost';
    private $db_name = 'u981836125_OhMyGudness1';
    private $username = 'u981836125_OhMyGudness1';
    private $password = 'OhMyGudness@1234';
    private $conn;

    public function getConnection()
    {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
                );

            // Set UTF8 encoding
            $this->conn->exec("SET NAMES utf8");

            // FORCE MySQL to use Indian Standard Time (IST)
            $this->conn->exec("SET time_zone = '+05:30'");

            // Enable PDO error mode
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
        catch (PDOException $exception) {
            error_log("Connection error: " . $exception->getMessage());
            return null;
        }
        return $this->conn;
    }
}

// Centralized authentication function for all backend files
function authenticate()
{
    if (isset($_SESSION['user_id'])) {
        return $_SESSION['user_id'];
    }

    http_response_code(401);
    echo json_encode(["message" => "Unauthorized - Please login"]);
    exit();
}
?>
