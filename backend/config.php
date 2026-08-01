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

session_start();

// 24-hour session inactivity auto-logout check (configurable via SESSION_TIMEOUT_SECONDS in .env)
$sessionTimeout = getenv('SESSION_TIMEOUT_SECONDS') ? (int)getenv('SESSION_TIMEOUT_SECONDS') : 86400; // 86400 seconds = 24 hours
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $sessionTimeout)) {
    session_unset();
    session_destroy();
    session_start();
    $_SESSION['session_expired'] = true;
}
$_SESSION['last_activity'] = time();

// Handle CORS dynamically
$allowedOriginsStr = getenv('ALLOWED_ORIGINS') !== false ? getenv('ALLOWED_ORIGINS') : (isset($_ENV['ALLOWED_ORIGINS']) ? $_ENV['ALLOWED_ORIGINS'] : '');
if (!empty($allowedOriginsStr)) {
    $allowedOrigins = array_map('trim', explode(',', $allowedOriginsStr));
} else {
    $allowedOrigins = [
        'http://localhost:5173',
        'http://localhost:5174',
        'http://localhost:3000',
        'http://localhost:8000'
    ];
}

// Automatically include configured BASE_URL / SITE_URL in allowed origins if set
$configuredBaseUrl = getenv('BASE_URL') ?: (getenv('VITE_SITE_URL') ?: getenv('VITE_BACKEND_URL'));
if ($configuredBaseUrl && !in_array($configuredBaseUrl, $allowedOrigins)) {
    $allowedOrigins[] = rtrim($configuredBaseUrl, '/');
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
        $this->host = (getenv('DB_HOST') !== false && getenv('DB_HOST') !== '') ? getenv('DB_HOST') : (isset($_ENV['DB_HOST']) ? $_ENV['DB_HOST'] : 'localhost');
        $this->db_name = (getenv('DB_NAME') !== false && getenv('DB_NAME') !== '') ? getenv('DB_NAME') : (isset($_ENV['DB_NAME']) ? $_ENV['DB_NAME'] : 'omg_db');
        $this->username = (getenv('DB_USER') !== false && getenv('DB_USER') !== '') ? getenv('DB_USER') : (isset($_ENV['DB_USER']) ? $_ENV['DB_USER'] : 'root');
        $this->password = (getenv('DB_PASS') !== false) ? getenv('DB_PASS') : (isset($_ENV['DB_PASS']) ? $_ENV['DB_PASS'] : '');
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

// Ensure Surprise Experience Builder & Pincode Tables Exist with Default Seed Data
function ensureSurpriseTablesExist($db)
{
    try {
        // 1. Base Experiences Table
        $db->exec("CREATE TABLE IF NOT EXISTS `surprise_experiences` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `title` VARCHAR(255) NOT NULL,
            `subtitle` VARCHAR(255),
            `description` TEXT,
            `badge` VARCHAR(100),
            `base_price` DECIMAL(10,2) NOT NULL,
            `image` VARCHAR(255) NOT NULL,
            `features` TEXT,
            `display_order` INT DEFAULT 0,
            `is_active` TINYINT(1) DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // Seed base experiences if empty
        $stmt = $db->query("SELECT COUNT(*) as cnt FROM `surprise_experiences`");
        if ($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] == 0) {
            $db->exec("INSERT INTO `surprise_experiences` (`title`, `subtitle`, `description`, `badge`, `base_price`, `image`, `features`, `display_order`, `is_active`) VALUES
            ('Romantic Rooftop Candlelight Surprise', 'Intimate 2-person candlelight dining under the stars', 'Transform any private terrace or rooftop into a fairytale setting with hundreds of flickering candles, plush cushions, live acoustic music, and gourmet dining.', 'Most Popular', 14999.00, 'https://miaoda-site-img.s3cdn.medo.dev/images/KLing_0d1f5bf9-c3e6-4678-b943-f496a294145d.jpg', '[\"2-Hour Private Setup\", \"Custom Candle Pathway\", \"Fresh Rose Petal Carpet\", \"Luxury Velvet Seating\", \"Personal Experience Coordinator\"]', 1, 1),
            ('Luxury Private Yacht & Lake Escape', 'Exclusive floating celebration on serene waters', 'Sail into luxury with a private decorated yacht or boat setup featuring premium floral garlands, chilled mocktails/beverages, and panoramic sunset views.', 'Bestseller', 24999.00, 'https://miaoda-site-img.s3cdn.medo.dev/images/KLing_71ed4da9-9ce1-4430-b4a5-15e4fbfc8ba3.jpg', '[\"Private Deck Access\", \"Gourmet Refreshments\", \"Sunset Floral Decor\", \"Dedicated Photographer Onboard\", \"Custom Message Canopy\"]', 2, 1),
            ('Bespoke Garden Fairy Canopy', 'Enchanted botanical setup with golden lights', 'A magical outdoor or backyard transformation featuring a glowing fairy-light canopy, exotic orchid arrangements, and a personalized milestone photo gallery.', 'Top Rated', 18499.00, 'https://miaoda-site-img.s3cdn.medo.dev/images/KLing_14558096-74be-4c1a-a8a2-e0334e6050d9.jpg', '[\"Fairy-Light Archway\", \"Exotic Floral Installations\", \"Milestone Photo Gallery\", \"Ambient Background Music\", \"Personalized Gift Box Included\"]', 3, 1),
            ('Private Cinema & Midnight Serenade', 'Exclusive theater setup for intimate screenings', 'Book a private screening room complete with popcorn bar, custom video tribute on the big screen, and a surprise midnight acoustic guitarist.', 'Most Romantic', 21999.00, 'https://miaoda-site-img.s3cdn.medo.dev/images/KLing_66ade087-5eac-4fb3-83db-431e8df6b554.jpg', '[\"Private Screen Room\", \"Custom Video Tribute Playback\", \"Gourmet Snack & Beverage Bar\", \"Live Guitarist Serenade\", \"Red Carpet Entrance\"]', 4, 1);");
        }

        // 2. Upgrades Table
        $db->exec("CREATE TABLE IF NOT EXISTS `surprise_upgrades` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(255) NOT NULL,
            `description` TEXT,
            `icon` VARCHAR(100) DEFAULT 'Sparkles',
            `image` VARCHAR(255),
            `price` DECIMAL(10,2) NOT NULL,
            `display_order` INT DEFAULT 0,
            `is_active` TINYINT(1) DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // Seed upgrades if empty
        $stmt = $db->query("SELECT COUNT(*) as cnt FROM `surprise_upgrades`");
        if ($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] == 0) {
            $db->exec("INSERT INTO `surprise_upgrades` (`name`, `description`, `icon`, `price`, `display_order`, `is_active`) VALUES
            ('Live Acoustic Guitarist', '30 minutes of personalized live serenades of favorite romantic tunes.', 'Music', 3500.00, 1, 1),
            ('Professional Photographer', '1 hour dedicated shoot with 20 edited high-res digital photographs.', 'Camera', 4500.00, 2, 1),
            ('Luxury Hampers & Keepsake Box', 'Handcrafted wooden memory chest filled with chocolates, floral scents & greeting scroll.', 'Gift', 5000.00, 3, 1),
            ('Cold Fire Sparklers & Pyro Entry', '4 cold-fire sparkler fountains triggered upon grand entry.', 'Sparkles', 2500.00, 4, 1),
            ('Heart Balloon Canopy', '100 helium heart balloons with hanging memory cards.', 'Heart', 3000.00, 5, 1);");
        }
    } catch (Exception $e) {
        error_log("Error in ensureSurpriseTablesExist: " . $e->getMessage());
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