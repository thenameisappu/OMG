SET time_zone = '+05:30';

-- 1. Users Table (handling Authentication)
CREATE TABLE IF NOT EXISTS `users` (
    `id` CHAR(36) PRIMARY KEY, -- UUID
    `email` VARCHAR(191) UNIQUE NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `is_verified` TINYINT(1) DEFAULT 0,
    `otp_code` VARCHAR(6),
    `otp_expiry` DATETIME,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. User Profiles Table
CREATE TABLE IF NOT EXISTS `user_profiles` (
    `id` CHAR(36) PRIMARY KEY, -- References users.id
    `name` VARCHAR(255),
    `phone` VARCHAR(20),
    `address` TEXT,
    `city` VARCHAR(100) DEFAULT 'Bangalore',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Products Table
CREATE TABLE IF NOT EXISTS `products` (
    `id` CHAR(36) PRIMARY KEY, -- UUID
    `name` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(191) UNIQUE NOT NULL,
    `description` TEXT,
    `price` DECIMAL(10, 2) NOT NULL,
    `category` VARCHAR(100),
    `image` VARCHAR(255),
    `hover_image` VARCHAR(255),
    `features` LONGTEXT, -- Storing array of strings as JSON
    `is_featured` BOOLEAN DEFAULT FALSE,
    `is_bestseller` BOOLEAN DEFAULT FALSE,
    `reviews_count` INT DEFAULT 0,
    `stock_status` ENUM('in_stock', 'out_of_stock') DEFAULT 'in_stock',
    `stock_quantity` INT DEFAULT 0,
    `is_active` TINYINT(1) DEFAULT 1,
    `sku` VARCHAR(100) DEFAULT NULL,
    `images` LONGTEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Orders Table
CREATE TABLE IF NOT EXISTS `orders` (
    `id` CHAR(36) PRIMARY KEY, -- UUID
    `user_id` CHAR(36) NOT NULL,
    `total_amount` DECIMAL(10, 2) NOT NULL,
    `customer_name` VARCHAR(255) NOT NULL,
    `customer_email` VARCHAR(255) NOT NULL,
    `customer_phone` VARCHAR(20) NOT NULL,
    `delivery_address` TEXT NOT NULL,    
    `city` VARCHAR(100) DEFAULT 'Bangalore',
    `delivery_option` VARCHAR(50) NOT NULL,
    `delivery_date` DATE,
    `delivery_time` VARCHAR(50),
    `payment_method` VARCHAR(50) NOT NULL,
    `status` VARCHAR(50) DEFAULT 'pending',
    `is_archived` TINYINT(1) NOT NULL DEFAULT 0, -- Soft-delete flag (admin archive). 1 = hidden from Admin Orders. Customer order history always shows all orders regardless of this flag.
    `archived_at` DATETIME DEFAULT NULL,          -- Timestamp when admin archived this order
    `archived_by` VARCHAR(100) DEFAULT NULL,      -- Admin username who archived the order
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`),
    INDEX `idx_orders_is_archived` (`is_archived`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Order Items Table
CREATE TABLE IF NOT EXISTS `order_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` CHAR(36) NOT NULL,
    `product_id` CHAR(36) NOT NULL,
    `quantity` INT NOT NULL,
    `unit_price` DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Wishlist Table
CREATE TABLE IF NOT EXISTS `wishlist` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` CHAR(36) NOT NULL,
    `product_id` CHAR(36) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_wishlist` (`user_id`, `product_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Admin Users Table (Full Credentials, Email & OTP)
CREATE TABLE IF NOT EXISTS `admin_users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(100) UNIQUE NOT NULL,
    `name` VARCHAR(255) DEFAULT NULL,
    `password` VARCHAR(255) NOT NULL,
    `email` VARCHAR(191) UNIQUE DEFAULT NULL,
    `is_main_admin` TINYINT(1) DEFAULT 0,
    `otp_code` VARCHAR(6) DEFAULT NULL,
    `otp_expiry` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Safely add missing columns to admin_users if importing into an existing database
ALTER TABLE `admin_users` ADD COLUMN `name` VARCHAR(255) AFTER `username`;
ALTER TABLE `admin_users` ADD COLUMN `email` VARCHAR(191) UNIQUE AFTER `password`;
ALTER TABLE `admin_users` ADD COLUMN `is_main_admin` TINYINT(1) DEFAULT 0 AFTER `email`;
ALTER TABLE `admin_users` ADD COLUMN `otp_code` VARCHAR(6) AFTER `is_main_admin`;
ALTER TABLE `admin_users` ADD COLUMN `otp_expiry` DATETIME AFTER `otp_code`;

-- 8. Inquiries Table (Surprise / Bespoke Service requests)
CREATE TABLE IF NOT EXISTS `inquiries` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `contact_no` VARCHAR(30) NOT NULL,
    `address` TEXT,
    `city` VARCHAR(100),
    `event_type` VARCHAR(100) NOT NULL,
    `service_name` VARCHAR(255) DEFAULT NULL,
    `message` TEXT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. Customisations Table (Oh My Customisation's requests)
CREATE TABLE IF NOT EXISTS `customisations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `contact_no` VARCHAR(30) NOT NULL,
    `address` TEXT,
    `city` VARCHAR(100),
    `event_type` VARCHAR(100) NOT NULL,
    `service_name` VARCHAR(255) DEFAULT NULL,
    `message` TEXT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 10. Newsletter Subscribers Table
CREATE TABLE IF NOT EXISTS `newsletter_subscribers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(191) UNIQUE NOT NULL,
    `subscribed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 11. Base Surprise Experiences Table
CREATE TABLE IF NOT EXISTS `surprise_experiences` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 12. Experience Upgrades Table
CREATE TABLE IF NOT EXISTS `surprise_upgrades` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `icon` VARCHAR(100) DEFAULT 'Sparkles',
    `image` VARCHAR(255),
    `price` DECIMAL(10,2) NOT NULL,
    `display_order` INT DEFAULT 0,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
