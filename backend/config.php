<?php

// Load environment variables from the .env file (checks backend/ first, then the root directory)
(static function () {
    $envPath = file_exists(__DIR__ . '/.env') ? __DIR__ . '/.env' : __DIR__ . '/../.env';
    if (!file_exists($envPath)) {
        return;
    }

    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) {
            continue;
        }

        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $key = trim($parts[0]);
            $val = trim($parts[1]);

            // Remove outer quotes if present
            if (
                strlen($val) >= 2 &&
                (($val[0] === '"' && $val[strlen($val) - 1] === '"') ||
                    ($val[0] === "'" && $val[strlen($val) - 1] === "'"))
            ) {
                $val = substr($val, 1, -1);
            }

            putenv("$key=$val");
            $_ENV[$key] = $val;
            $_SERVER[$key] = $val;
        }
    }
})();

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
$allowedOriginsStr = getenv('ALLOWED_ORIGINS') ?: ($_ENV['ALLOWED_ORIGINS'] ?? '');
if (!empty($allowedOriginsStr)) {
    $allowedOrigins = array_map('trim', explode(',', $allowedOriginsStr));
} else {
    $allowedOrigins = [
        'http://localhost:5173',
        'http://localhost:3000',
        'http://localhost:8000'
    ];
}

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
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $conn;

    public function __construct()
    {
        $this->host = getenv('DB_HOST') !== false ? getenv('DB_HOST') : ($_ENV['DB_HOST'] ?? 'localhost');
        $this->db_name = getenv('DB_NAME') !== false ? getenv('DB_NAME') : ($_ENV['DB_NAME'] ?? '');
        $this->username = getenv('DB_USER') !== false ? getenv('DB_USER') : ($_ENV['DB_USER'] ?? '');
        $this->password = getenv('DB_PASS') !== false ? getenv('DB_PASS') : ($_ENV['DB_PASS'] ?? '');
    }

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
        } catch (PDOException $exception) {
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