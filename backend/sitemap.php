<?php
/**
 * Dynamic XML Sitemap Generator for Oh My Gudness
 * Fetches all active products and categories in real-time from the database.
 */

require_once __DIR__ . '/config.php';

header('Content-Type: application/xml; charset=utf-8');

$host = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'ohmygudness.in');
$today = date('Y-m-d');

$database = new Database();
$db = $database->getConnection();

$products = [];
if ($db) {
    try {
        $stmt = $db->query("SELECT slug, name, image, images, updated_at, created_at FROM products WHERE is_active = 1 AND category NOT IN ('surprise_experience', 'subscription') ORDER BY id DESC");
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $products = [];
    }
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
  <url>
    <loc><?php echo $host; ?>/</loc>
    <lastmod><?php echo $today; ?></lastmod>
    <changefreq>daily</changefreq>
    <priority>1.0</priority>
  </url>
  <url>
    <loc><?php echo $host; ?>/products</loc>
    <lastmod><?php echo $today; ?></lastmod>
    <changefreq>daily</changefreq>
    <priority>0.9</priority>
  </url>
  <url>
    <loc><?php echo $host; ?>/products?category=flower-arrangements</loc>
    <lastmod><?php echo $today; ?></lastmod>
    <changefreq>weekly</changefreq>
    <priority>0.85</priority>
  </url>
  <url>
    <loc><?php echo $host; ?>/products?category=gift-hampers</loc>
    <lastmod><?php echo $today; ?></lastmod>
    <changefreq>weekly</changefreq>
    <priority>0.85</priority>
  </url>
  <url>
    <loc><?php echo $host; ?>/products?category=signature-collection</loc>
    <lastmod><?php echo $today; ?></lastmod>
    <changefreq>weekly</changefreq>
    <priority>0.85</priority>
  </url>
  <url>
    <loc><?php echo $host; ?>/products?category=occasions</loc>
    <lastmod><?php echo $today; ?></lastmod>
    <changefreq>weekly</changefreq>
    <priority>0.85</priority>
  </url>
  <url>
    <loc><?php echo $host; ?>/products?category=custom-orders</loc>
    <lastmod><?php echo $today; ?></lastmod>
    <changefreq>weekly</changefreq>
    <priority>0.85</priority>
  </url>
  <url>
    <loc><?php echo $host; ?>/surprise-services</loc>
    <lastmod><?php echo $today; ?></lastmod>
    <changefreq>weekly</changefreq>
    <priority>0.9</priority>
  </url>
  <url>
    <loc><?php echo $host; ?>/subscriptions</loc>
    <lastmod><?php echo $today; ?></lastmod>
    <changefreq>weekly</changefreq>
    <priority>0.8</priority>
  </url>
  <url>
    <loc><?php echo $host; ?>/about</loc>
    <lastmod><?php echo $today; ?></lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.7</priority>
  </url>
  <url>
    <loc><?php echo $host; ?>/faq</loc>
    <lastmod><?php echo $today; ?></lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.7</priority>
  </url>
  <url>
    <loc><?php echo $host; ?>/delivery-info</loc>
    <lastmod><?php echo $today; ?></lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.7</priority>
  </url>
  <url>
    <loc><?php echo $host; ?>/terms</loc>
    <lastmod><?php echo $today; ?></lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.5</priority>
  </url>

<?php foreach ($products as $p): 
    if (empty($p['slug'])) continue;
    $pUrl = $host . '/product/' . urlencode($p['slug']);
    $mod = !empty($p['updated_at']) ? substr($p['updated_at'], 0, 10) : (!empty($p['created_at']) ? substr($p['created_at'], 0, 10) : $today);
    $img = $p['image'] ?? '';
    if (empty($img) && !empty($p['images'])) {
        $arr = json_decode($p['images'], true);
        if (is_array($arr) && !empty($arr[0])) $img = $arr[0];
    }
    if ($img && strpos($img, 'http') !== 0) {
        $img = $host . (strpos($img, '/') === 0 ? '' : '/') . $img;
    }
?>
  <url>
    <loc><?php echo htmlspecialchars($pUrl); ?></loc>
    <lastmod><?php echo $mod; ?></lastmod>
    <changefreq>weekly</changefreq>
    <priority>0.8</priority>
<?php if ($img): ?>
    <image:image>
      <image:loc><?php echo htmlspecialchars($img); ?></image:loc>
      <image:title><?php echo htmlspecialchars($p['name']); ?></image:title>
    </image:image>
<?php endif; ?>
  </url>
<?php endforeach; ?>
</urlset>

