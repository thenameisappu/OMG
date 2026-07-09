import { Product } from '@/types';

export const mockProducts: Product[] = [
  {
    id: '6f8c2f0a-3c2a-4fbb-b8f2-9a7d1c2e5a11',
    name: 'Midnight Velvet Roses',
    slug: 'midnight-velvet-roses',
    description: 'Deep crimson roses accented with lush eucalyptus and gold-dusted foliage. A symbol of eternal elegance.',
    price: 10375.00,
    category: 'flower-arrangements',
    image: 'https://miaoda-site-img.s3cdn.medo.dev/images/KLing_8fb5dcf8-22bd-4fbd-98ba-1611bfcdcc4d.jpg',
    hover_image: 'https://miaoda-site-img.s3cdn.medo.dev/images/KLing_71ed4da9-9ce1-4430-b4a5-15e4fbfc8ba3.jpg',
    features: ['Premium Long-stem Roses', 'Luxury Velvet Wrap', 'Hand-delivered'],
    is_featured: true,
    stock_status: 'in_stock'
  },
  {
    id: 'a9c3d6e1-8b44-4c0a-9f0a-6d3c9fbb2e77',
    name: 'Royal Orchid Symphony',
    slug: 'royal-orchid-symphony',
    description: 'A breathtaking arrangement of white phalaenopsis orchids in a handcrafted ceramic vase.',
    price: 15355.00,
    category: 'flower-arrangements',
    image: 'https://miaoda-site-img.s3cdn.medo.dev/images/KLing_e693fad0-d350-4067-888a-6f8fc1397c26.jpg',
    hover_image: 'https://miaoda-site-img.s3cdn.medo.dev/images/KLing_e693fad0-d350-4067-888a-6f8fc1397c26.jpg',
    features: ['Exotic White Orchids', 'Ceramic Vase Included', 'Same-day Delivery'],
    is_featured: true,
    stock_status: 'in_stock'
  },
  {
    id: 'd2f9e7b4-0c11-4e2c-8c7a-2f9a6d4b1e55',
    name: 'Golden Celebration Hamper',
    slug: 'golden-celebration-hamper',
    description: 'Curated luxury including artisan chocolates, premium champagne, and a miniature seasonal bouquet.',
    price: 20750.00,
    category: 'gift-hampers',
    image: 'https://miaoda-site-img.s3cdn.medo.dev/images/KLing_de270258-e087-4e07-b20f-8e364b1eda36.jpg',
    hover_image: 'https://miaoda-site-img.s3cdn.medo.dev/images/KLing_de270258-e087-4e07-b20f-8e364b1eda36.jpg',
    features: ['Premium Champagne', 'Artisan Chocolates', 'Silk Ribbon Finish'],
    is_featured: true,
    stock_status: 'in_stock'
  },
  {
    id: 'b7a1d9c3-5e22-4f88-b1aa-9d44c6e2f999',
    name: 'Emerald Fern Terrarium',
    slug: 'emerald-fern-terrarium',
    description: 'A self-sustaining ecosystem in a modern glass geometric vessel.',
    price: 7885.00,
    category: 'plants',
    image: 'https://miaoda-site-img.s3cdn.medo.dev/images/KLing_57486cc2-7282-4aef-bc36-bfe2603771aa.jpg',
    hover_image: 'https://miaoda-site-img.s3cdn.medo.dev/images/KLing_57486cc2-7282-4aef-bc36-bfe2603771aa.jpg',
    features: ['Low Maintenance', 'Hand-blown Glass', 'Modern Design'],
    is_featured: false,
    stock_status: 'in_stock'
  },

  {
    id: 'c1e8a6f0-7b55-42a9-9eaa-123456789001',
    name: 'Silk Peony Bloom',
    slug: 'silk-peony-bloom',
    description: 'Soft pastel peonies gathered in a classic round bouquet.',
    price: 9130.00,
    category: 'flower-arrangements',
    image: 'https://miaoda-site-img.s3cdn.medo.dev/images/KLing_91478dab-3e76-4e57-bfb6-7cdb0ffcc12f.jpg',
    hover_image: 'https://miaoda-site-img.s3cdn.medo.dev/images/KLing_91478dab-3e76-4e57-bfb6-7cdb0ffcc12f.jpg',
    features: ['Fresh Seasonal Peonies', 'Silk Ribbon', 'Fragrance Card'],
    is_featured: false,
    stock_status: 'in_stock'
  },

  {
    id: 'f4d3a7b2-88a4-4cde-9f77-987654321002',
    name: 'Luxury Scented Candle',
    slug: 'luxury-scented-candle',
    description: 'Hand-poured soy candle with notes of jasmine and sandalwood.',
    price: 3735.00,
    category: 'add-on-gifts',
    image: 'https://miaoda-site-img.s3cdn.medo.dev/images/KLing_6bbe1cd4-2103-4b1e-b55e-83ffbca65dd2.jpg',
    hover_image: 'https://miaoda-site-img.s3cdn.medo.dev/images/KLing_6bbe1cd4-2103-4b1e-b55e-83ffbca65dd2.jpg',
    features: ['50 Hour Burn Time', 'Natural Soy Wax', 'Luxury Packaging'],
    is_featured: false,
    stock_status: 'in_stock'
  },

  {
    id: 'aa92d8b1-1111-4c22-aaaa-abcdef123456',
    name: 'Sunset Garden Bouquet',
    slug: 'sunset-garden-bouquet',
    description: 'Vibrant mix of orange lilies, yellow roses, and seasonal blooms.',
    price: 8300.00,
    category: 'flower-arrangements',
    image: 'https://miaoda-site-img.s3cdn.medo.dev/images/KLing_8fb5dcf8-22bd-4fbd-98ba-1611bfcdcc4d.jpg',
    hover_image: 'https://miaoda-site-img.s3cdn.medo.dev/images/KLing_8fb5dcf8-22bd-4fbd-98ba-1611bfcdcc4d.jpg',
    features: ['Fresh Cut Flowers', 'Designer Arrangement', 'Same-day Delivery'],
    is_featured: false,
    stock_status: 'in_stock'
  },

  {
    id: 'bb33c9d2-2222-4c22-bbbb-fedcba654321',
    name: 'Premium Gourmet Hamper',
    slug: 'premium-gourmet-hamper',
    description: 'Exquisite selection of imported delicacies and fine wines.',
    price: 18500.00,
    category: 'gift-hampers',
    image: 'https://miaoda-site-img.s3cdn.medo.dev/images/KLing_de270258-e087-4e07-b20f-8e364b1eda36.jpg',
    hover_image: 'https://miaoda-site-img.s3cdn.medo.dev/images/KLing_de270258-e087-4e07-b20f-8e364b1eda36.jpg',
    features: ['Imported Delicacies', 'Premium Wine', 'Elegant Basket'],
    is_featured: false,
    stock_status: 'in_stock'
  },

  {
    id: 'cc44d0e3-3333-4c22-cccc-001122334455',
    name: 'Birthday Celebration Bundle',
    slug: 'birthday-celebration-bundle',
    description: 'Complete birthday package with flowers and gifts.',
    price: 14900.00,
    category: 'occasions',
    image: 'https://miaoda-site-img.s3cdn.medo.dev/images/KLing_3556e18d-69b0-4c22-93c1-29efba584217.jpg',
    hover_image: 'https://miaoda-site-img.s3cdn.medo.dev/images/KLing_3556e18d-69b0-4c22-93c1-29efba584217.jpg',
    features: ['Complete Package', 'Balloons Included'],
    is_featured: false,
    stock_status: 'in_stock'
  }

];