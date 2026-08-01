-- =============================================
-- OMG (Oh My Gudness) - Dummy Product Seed Data
-- Run this SQL in your MySQL / Hostinger phpMyAdmin
-- Images: Unsplash public URLs (800x800 square recommended)
-- Product image ideal dimensions: 800 x 800 px (1:1 ratio)
-- =============================================

INSERT IGNORE INTO products
  (id, name, slug, description, price, category, image, hover_image, features,
   is_featured, is_bestseller, reviews_count, stock_status, stock_quantity, is_active, sku)
VALUES

-- ============================
-- OH MY BLOOM'S (flower-arrangements)
-- ============================
(
  'prod-001-bloom-rose',
  'Crimson Romance Bouquet',
  'crimson-romance-bouquet',
  'A breathtaking arrangement of 24 premium red roses, hand-tied with baby breath and eucalyptus. Perfect for anniversaries and expressing deep love.',
  1299.00, 'flower-arrangements',
  'https://images.unsplash.com/photo-1487530811015-780f2f97b548?w=800&q=80',
  'https://images.unsplash.com/photo-1518335935020-cfd87ec0f4dc?w=800&q=80',
  '["24 Premium Red Roses","Baby Breath Accent","Fresh Eucalyptus","Satin Ribbon","Handwritten Message Card","Free Same-Day Delivery"]',
  1, 1, 48, 'in_stock', 15, 1, 'BLOOM-001'
),
(
  'prod-002-bloom-mixed',
  'Pastel Dreamer Collection',
  'pastel-dreamer-collection',
  'An elegant mix of soft pink roses, white lisianthus, peach carnations, and lavender. This whimsical arrangement brings poetry to any occasion.',
  1599.00, 'flower-arrangements',
  'https://images.unsplash.com/photo-1490750967868-88df5691cc0e?w=800&q=80',
  'https://images.unsplash.com/photo-1586348943529-beaae6c28db9?w=800&q=80',
  '["Soft Pink Roses","White Lisianthus","Peach Carnations","Lavender Sprigs","Kraft Paper Wrapping","Gift Box Included"]',
  1, 0, 32, 'in_stock', 10, 1, 'BLOOM-002'
),
(
  'prod-003-bloom-sunflower',
  'Golden Sunshine Arrangement',
  'golden-sunshine-arrangement',
  'Brighten someone day with this vibrant collection of fresh sunflowers, yellow tulips, and orange gerberas. Pure joy in a vase.',
  999.00, 'flower-arrangements',
  'https://images.unsplash.com/photo-1533616688419-b7a585564566?w=800&q=80',
  'https://images.unsplash.com/photo-1591886960571-74d43a9d4166?w=800&q=80',
  '["12 Fresh Sunflowers","Yellow Tulips","Orange Gerberas","Rustic Jute Wrap","Personalised Tag"]',
  0, 1, 67, 'in_stock', 20, 1, 'BLOOM-003'
),
(
  'prod-004-bloom-orchid',
  'Royal Orchid Elegance',
  'royal-orchid-elegance',
  'Luxurious purple and white orchids arranged in a premium glass vase. Long-lasting and strikingly beautiful — a statement of sophistication.',
  2499.00, 'flower-arrangements',
  'https://images.unsplash.com/photo-1566898336292-b1dd7b06f02a?w=800&q=80',
  'https://images.unsplash.com/photo-1599598425947-5202edd56fdb?w=800&q=80',
  '["Premium Orchid Stems","Glass Vase Included","Lasts 2-3 Weeks","White and Purple Blooms","Luxury Gift Wrap"]',
  1, 1, 21, 'in_stock', 8, 1, 'BLOOM-004'
),

-- ============================
-- OH MY LOVE'S (gift-hampers)
-- ============================
(
  'prod-005-love-luxury',
  'Luxury Celebration Hamper',
  'luxury-celebration-hamper',
  'The ultimate gifting experience. Includes premium chocolates, scented candles, bath salts, artisan cookies, and a hand-tied rose bouquet in an elegant wooden crate.',
  3499.00, 'gift-hampers',
  'https://images.unsplash.com/photo-1549465220-1a8b9238cd48?w=800&q=80',
  'https://images.unsplash.com/photo-1607344645866-009c320b63e0?w=800&q=80',
  '["Belgian Chocolate Box","Scented Soy Candle","Bath Salt Set","Artisan Cookies","Mini Rose Bouquet","Wooden Gift Crate","Personalised Card"]',
  1, 1, 89, 'in_stock', 12, 1, 'LOVE-001'
),
(
  'prod-006-love-romance',
  'Romance and Roses Combo',
  'romance-and-roses-combo',
  'Say it with roses and chocolate! A curated pairing of 12 red roses with a box of premium dark chocolates and a heartfelt greeting card.',
  1899.00, 'gift-hampers',
  'https://images.unsplash.com/photo-1582845512747-e42001c95638?w=800&q=80',
  'https://images.unsplash.com/photo-1589820296156-2454bb8a6ad1?w=800&q=80',
  '["12 Red Roses","Premium Dark Chocolates","Greeting Card","Satin Bow","Express Delivery"]',
  0, 1, 54, 'in_stock', 18, 1, 'LOVE-002'
),

-- ============================
-- OH MY SIGNATURE'S (signature-collection)
-- ============================
(
  'prod-007-sig-cascade',
  'OMG Signature Cascade',
  'omg-signature-cascade',
  'Our flagship creation — a cascading waterfall of garden roses, ranunculus, anemones, and trailing foliage. Exclusively crafted by our head florist.',
  4999.00, 'signature-collection',
  'https://images.unsplash.com/photo-1455659817273-f96807779a8a?w=800&q=80',
  'https://images.unsplash.com/photo-1513519245088-0e12902e35ca?w=800&q=80',
  '["Garden Roses","Ranunculus","Anemones","Trailing Foliage","Hand-crafted by Head Florist","Certificate of Authenticity","Luxury Box Presentation"]',
  1, 0, 15, 'in_stock', 5, 1, 'SIG-001'
),
(
  'prod-008-sig-eternal',
  'Eternal Bloom Box',
  'eternal-bloom-box',
  'Preserved roses that last up to a year. Arranged in our signature OMG hatbox — a timeless keepsake for milestone moments.',
  3999.00, 'signature-collection',
  'https://images.unsplash.com/photo-1612540139150-4b70b8c5d85e?w=800&q=80',
  'https://images.unsplash.com/photo-1531089338241-b3b85a044b0c?w=800&q=80',
  '["Preserved Roses (Lasts 1 Year)","OMG Signature Hatbox","Velvet Interior","Metallic Rose Gold Finish","Free Engraving Available"]',
  1, 1, 38, 'in_stock', 7, 1, 'SIG-002'
),

-- ============================
-- OH MY CELEBRATION'S (occasions)
-- ============================
(
  'prod-009-occ-birthday',
  'Birthday Bloom Surprise',
  'birthday-bloom-surprise',
  'Make their birthday unforgettable! A vibrant medley of birthday-themed balloons, colourful seasonal flowers, and a mini cake — all delivered together.',
  2299.00, 'occasions',
  'https://images.unsplash.com/photo-1464349095431-e9a21285b5f3?w=800&q=80',
  'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=800&q=80',
  '["Seasonal Mixed Flowers","Birthday Balloons (5)","Mini Celebration Cake","Personalised Banner","Same-Day Delivery Available"]',
  1, 1, 76, 'in_stock', 14, 1, 'OCC-001'
),
(
  'prod-010-occ-wedding',
  'Bridal Bliss Package',
  'bridal-bliss-package',
  'Complete bridal florals for your big day. Includes bridal bouquet, 2 bridesmaid posies, boutonniere, and ceremony table centrepiece. Fully customisable.',
  9999.00, 'occasions',
  'https://images.unsplash.com/photo-1519225421980-715cb0215aed?w=800&q=80',
  'https://images.unsplash.com/photo-1511795409834-ef04bbd61622?w=800&q=80',
  '["Bridal Bouquet","2 Bridesmaid Posies","Boutonniere","Table Centrepiece","Fully Customisable","Consultation Included","Delivery and Setup"]',
  1, 0, 12, 'in_stock', 3, 1, 'OCC-002'
);

-- Verify
SELECT id, name, category, price, is_featured, is_bestseller, stock_status
FROM products
WHERE sku LIKE 'BLOOM-%' OR sku LIKE 'LOVE-%' OR sku LIKE 'SIG-%' OR sku LIKE 'OCC-%'
ORDER BY category, price;
