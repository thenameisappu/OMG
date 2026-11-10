<?php
/**
 * Dynamic OpenGraph & Social Crawler Gateway for Oh My Gudness (React SPA)
 * 
 * Serves index.html directly to human browsers, but intercepts social media crawlers
 * (WhatsApp, Facebook, Instagram, Twitter, LinkedIn, Telegram, etc.) to dynamically inject
 * exact product/category OpenGraph meta tags, titles, and images.
 */

$userAgent = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
$isCrawler = preg_match(
    '/(facebookexternalhit|facebot|whatsapp|twitterbot|linkedinbot|telegrambot|slackbot|discordbot|skypeuripreview|pinterest|googlebot|bingbot|applebot)/i',
    $userAgent
);

$htmlFile = __DIR__ . '/index.html';
if (!file_exists($htmlFile)) {
    http_response_code(404);
    echo "Site temporarily unavailable.";
    exit;
}

// 1. If human user, serve standard React SPA HTML directly
if (!$isCrawler) {
    header('Content-Type: text/html; charset=UTF-8');
    readfile($htmlFile);
    exit;
}

// 2. Crawler detected: determine requested entity
$html = file_get_contents($htmlFile);
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$host = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'ohmygudness.in');

$ogTitle = "OMG – Oh My Gudness | Luxury Floral Arrangements & Gifts in Bangalore";
$ogDesc = "Bangalore's premier floral & surprise house. Handcrafted fresh bouquets, curated luxury hampers, and bespoke surprise experiences with guaranteed 2-hour express delivery.";
$ogImage = $host . "/images/hero/hero_luxury_store.jpg";
$ogUrl = $host . $requestUri;
$ogType = "website";

// Extract possible product slug or category from URL or Query
$slug = '';
$category = '';

if (preg_match('#/(?:product|products)/([a-zA-Z0-9_-]+)#', $requestUri, $matches)) {
    $slug = $matches[1];
} elseif (isset($_GET['product'])) {
    $slug = trim($_GET['product']);
} elseif (isset($_GET['slug'])) {
    $slug = trim($_GET['slug']);
}

if (isset($_GET['category'])) {
    $category = trim($_GET['category']);
}

// Category mappings
$categoryMeta = [
    'flower-arrangements' => [
        'title' => "Oh My Bloom's – Fresh Floral Bouquets & Luxury Stems | OMG",
        'desc' => "Exquisite floral arrangements crafted with fresh farm-direct stems by master florists for life's finest moments.",
        'image' => $host . "/images/banners/banner_blooms.jpg"
    ],
    'gift-hampers' => [
        'title' => "Oh My Love's – Curated Hampers & Gourmet Treats | OMG",
        'desc' => "Curated luxury hampers featuring artisan delicacies, fine treats, and velvet ribbons designed to express your deepest sentiment.",
        'image' => $host . "/images/banners/banner_loves.jpg"
    ],
    'signature-collection' => [
        'title' => "Oh My Signature's – Rare Exotic Blooms & Bespoke Vessels | OMG",
        'desc' => "Our flagship collection of rare exotic blooms, handcrafted ceramic vessels, and statement architectural florals.",
        'image' => $host . "/images/banners/banner_signatures.jpg"
    ],
    'occasions' => [
        'title' => "Oh My Moment's – Celebration Bundles & Occasion Styling | OMG",
        'desc' => "Handcrafted floral masterpieces tailored for birthdays, anniversaries, proposals, and royal celebrations.",
        'image' => $host . "/images/banners/banner_moments.jpg"
    ],
    'custom-orders' => [
        'title' => "Oh My Customisation's – Bespoke Made-to-Order Floristry | OMG",
        'desc' => "Bespoke floral & hamper designs created exclusively for your vision with personalized consultations.",
        'image' => $host . "/images/banners/banner_customisations.jpg"
    ]
];

// Check if it's a category request
if ($category && isset($categoryMeta[$category])) {
    $ogTitle = $categoryMeta[$category]['title'];
    $ogDesc = $categoryMeta[$category]['desc'];
    $ogImage = $categoryMeta[$category]['image'];
} elseif (strpos($requestUri, 'surprise-services') !== false) {
    $ogTitle = "Bespoke Surprise & Proposal Planning Services in Bangalore | OMG";
    $ogDesc = "Experience the magic of Oh My Moment's. Rooftop candlelight dinners, secret proposals, private yachts, fairy-light canopies & midnight serenades orchestrated in Bangalore.";
    $ogImage = $host . "/images/hero/hero_surprise_moment.jpg";
} elseif ($slug) {
    // Try to lookup product from DB if available
    try {
        $configFile = __DIR__ . '/backend/config.php';
        if (!file_exists($configFile)) {
            $configFile = __DIR__ . '/../backend/config.php';
        }
        if (file_exists($configFile)) {
            require_once $configFile;
            if (class_exists('Database')) {
                $database = new Database();
                $db = $database->getConnection();
                if ($db) {
                    $stmt = $db->prepare("SELECT name, description, price, image, images FROM products WHERE slug = :slug OR id = :id LIMIT 1");
                    $stmt->execute([':slug' => $slug, ':id' => $slug]);
                    $prod = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($prod) {
                        $ogTitle = htmlspecialchars($prod['name']) . " – ₹" . number_format($prod['price']) . " | Oh My Gudness Bangalore";
                        $cleanDesc = trim(strip_tags($prod['description'] ?? ''));
                        if (strlen($cleanDesc) > 160) {
                            $cleanDesc = substr($cleanDesc, 0, 157) . '...';
                        }
                        $ogDesc = $cleanDesc ?: "Order " . htmlspecialchars($prod['name']) . " handcrafted with fresh farm-direct stems. Express same-day delivery across Bangalore.";
                        
                        $img = $prod['image'] ?: '';
                        if (empty($img) && !empty($prod['images'])) {
                            $decoded = json_decode($prod['images'], true);
                            if (is_array($decoded) && !empty($decoded[0])) {
                                $img = $decoded[0];
                            }
                        }
                        if ($img) {
                            $ogImage = (strpos($img, 'http') === 0) ? $img : ($host . (strpos($img, '/') === 0 ? '' : '/') . $img);
                        }
                        $ogType = "product";
                    }
                }
            }
        }
    } catch (Exception $e) {
        // Fallback gracefully to default
    }
}

// 3. Replace default OpenGraph & Meta Tags with Dynamic Content
$html = preg_replace('/<title>.*?<\/title>/i', '<title>' . $ogTitle . '</title>', $html);
$html = preg_replace('/<meta\s+name=["\']description["\']\s+content=["\'].*?["\']\s*\/?>/i', '<meta name="description" content="' . htmlspecialchars($ogDesc) . '" />', $html);

// OpenGraph tags replacement
$html = preg_replace('/<meta\s+property=["\']og:title["\']\s+content=["\'].*?["\']\s*\/?>/i', '<meta property="og:title" content="' . htmlspecialchars($ogTitle) . '" />', $html);
$html = preg_replace('/<meta\s+property=["\']og:description["\']\s+content=["\'].*?["\']\s*\/?>/i', '<meta property="og:description" content="' . htmlspecialchars($ogDesc) . '" />', $html);
$html = preg_replace('/<meta\s+property=["\']og:image["\']\s+content=["\'].*?["\']\s*\/?>/i', '<meta property="og:image" content="' . htmlspecialchars($ogImage) . '" />', $html);
$html = preg_replace('/<meta\s+property=["\']og:image:secure_url["\']\s+content=["\'].*?["\']\s*\/?>/i', '<meta property="og:image:secure_url" content="' . htmlspecialchars($ogImage) . '" />', $html);
$html = preg_replace('/<meta\s+property=["\']og:url["\']\s+content=["\'].*?["\']\s*\/?>/i', '<meta property="og:url" content="' . htmlspecialchars($ogUrl) . '" />', $html);
$html = preg_replace('/<meta\s+property=["\']og:type["\']\s+content=["\'].*?["\']\s*\/?>/i', '<meta property="og:type" content="' . htmlspecialchars($ogType) . '" />', $html);

// Twitter tags replacement
$html = preg_replace('/<meta\s+name=["\']twitter:title["\']\s+content=["\'].*?["\']\s*\/?>/i', '<meta name="twitter:title" content="' . htmlspecialchars($ogTitle) . '" />', $html);
$html = preg_replace('/<meta\s+name=["\']twitter:description["\']\s+content=["\'].*?["\']\s*\/?>/i', '<meta name="twitter:description" content="' . htmlspecialchars($ogDesc) . '" />', $html);
$html = preg_replace('/<meta\s+name=["\']twitter:image["\']\s+content=["\'].*?["\']\s*\/?>/i', '<meta name="twitter:image" content="' . htmlspecialchars($ogImage) . '" />', $html);

// Output HTML to crawler
header('Content-Type: text/html; charset=UTF-8');
echo $html;
exit;

