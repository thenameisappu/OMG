SET time_zone = '+05:30';
SELECT CURRENT_TIMESTAMP AS indian_time;

-- Users Table (handling Authentication)
CREATE TABLE IF NOT EXISTS `users` (
    `id` CHAR(36) PRIMARY KEY, -- UUID
    `email` VARCHAR(255) UNIQUE NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `is_verified` TINYINT(1) DEFAULT 0,
    `otp_code` VARCHAR(6),
    `otp_expiry` DATETIME,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- User Profiles Table
CREATE TABLE IF NOT EXISTS `user_profiles` (
    `id` CHAR(36) PRIMARY KEY, -- References users.id
    `name` VARCHAR(255),
    `phone` VARCHAR(20),
    `address` TEXT,
    `city` VARCHAR(100) DEFAULT 'Bangalore',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

-- Products Table
CREATE TABLE IF NOT EXISTS `products` (
    `id` CHAR(36) PRIMARY KEY, -- UUID
    `name` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(255) UNIQUE NOT NULL,
    `description` TEXT,
    `price` DECIMAL(10, 2) NOT NULL,
    `category` VARCHAR(100),
    `image` VARCHAR(255),
    `hover_image` VARCHAR(255),
    `features` JSON, -- Storing array of strings as JSON
    `is_featured` BOOLEAN DEFAULT FALSE,
    `is_bestseller` BOOLEAN DEFAULT FALSE,
    `reviews_count` INT DEFAULT 0,
    `stock_status` ENUM('in_stock', 'out_of_stock') DEFAULT 'in_stock',
    `stock_quantity` INT DEFAULT 0,
    `is_active` TINYINT(1) DEFAULT 1,
    `sku` VARCHAR(100) DEFAULT NULL,
    `images` JSON DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Orders Table
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
    `status` ENUM('pending','confirmed','processing','shipped','delivered','cancelled') DEFAULT 'pending',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
);

-- Order Items Table
CREATE TABLE IF NOT EXISTS `order_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` CHAR(36) NOT NULL,
    `product_id` CHAR(36) NOT NULL,
    `quantity` INT NOT NULL,
    `unit_price` DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)
);

-- Wishlist Table
CREATE TABLE IF NOT EXISTS `wishlist` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` CHAR(36) NOT NULL,
    `product_id` CHAR(36) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_wishlist` (`user_id`, `product_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
);

-- Admin Users Table
CREATE TABLE IF NOT EXISTS `admin_users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) UNIQUE NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Inquiries Table (Surprise / Bespoke Service requests)
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
);

-- Customisations Table (Oh My Customisation's requests)
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
);

-- Newsletter Subscribers Table
CREATE TABLE IF NOT EXISTS `newsletter_subscribers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(255) UNIQUE NOT NULL,
    `subscribed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

