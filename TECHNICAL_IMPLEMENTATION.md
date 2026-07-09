# OMG E-commerce Backend Integration - Technical Implementation Plan (PHP/MySQL)

## Overview
This document outlines the complete backend-database integration for the OMG (Oh My Gudness) luxury e-commerce platform using a custom PHP backend and MySQL database.

## 1. Database Architecture

### 1.1 Connection
- **Host**: auth-db2201.hstgr.io
- **Database**: u981836125_OhMyGudness1
- **User**: u981836125_OhMyGudness1
- **Type**: MySQL / MariaDB

### 1.2 Tables ( Assumed based on usage)
- `users` (id, email, password_hash)
- `user_profiles` (id, name, phone, address, city)
- `products` (id, name, slug, description, price, category, inventory_count, image_url, rating, reviews_count, is_bestseller)
- `orders` (id, user_id, total_amount, status, created_at, ...)
- `order_items` (id, order_id, product_id, quantity, unit_price)
- `wishlist` (id, user_id, product_id, created_at)

## 2. Authentication System

### 2.1 PHP Auth Implementation
- **Files**: `backend/auth.php`
- **Method**: JWT-like token (Base64 encoded ID + Timestamp for demo) or Session based.
- **Endpoints**:
  - `POST /auth.php?action=register`
  - `POST /auth.php?action=login`
  - `GET /auth.php?action=get_user`

### 2.2 Frontend Integration
- **Service**: `src/services/api.ts` (authService)
- **Token Storage**: `localStorage` ('auth_token')

## 3. API Functions & Endpoints

### 3.1 Base URL
`http://localhost/OMG-main/backend` (or configured URL)

### 3.2 Products
- **File**: `backend/products.php`
- `GET /products.php?action=get_products&category={cat}`
- `GET /products.php?action=get_product&slug={slug}`
- `GET /products.php?action=get_featured`
- `GET /products.php?action=search&search={term}`

### 3.3 Orders
- **File**: `backend/orders.php`
- `POST /orders.php?action=create_order`
- `GET /orders.php?action=get_orders`
- `GET /orders.php?action=get_order&id={id}`

### 3.4 Wishlist
- **File**: `backend/wishlist.php`
- `GET /wishlist.php?action=get_wishlist`
- `POST /wishlist.php?action=add` (Body: product_id)
- `GET /wishlist.php?action=remove&product_id={id}`
- `GET /wishlist.php?action=check&product_id={id}`

### 3.5 File Uploads
- **File**: `backend/upload.php`
- `POST /backend/upload.php`
- **Frontend Hook**: `src/hooks/use-upload.ts`

## 4. Frontend Integration

### 4.1 Service Layer
All API calls are centralized in `src/services/api.ts`, using `axios` with interceptors to inject the Authorization header.

### 4.2 Components
Components import services from `@/services/api` or use custom hooks like `use-upload` to interact with the backend.

## 5. Security
- **CORS**: Enabled in PHP headers.
- **Input Validation**: Basic validation in PHP scripts.
- **Password Hashing**: `password_hash` (Bcrypt) used in `auth.php`.
- **SQL Injection**: `PDO` with prepared statements used globally.
