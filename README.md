# OMG (Oh My Gudness) — Luxury Boutique E-Commerce Platform

[![React](https://img.shields.io/badge/React-18.0.0-blue.svg?logo=react&logoColor=white)](https://react.dev/)
[![Vite](https://img.shields.io/badge/Vite-5.1.4-646CFF.svg?logo=vite&logoColor=white)](https://vitejs.dev/)
[![PHP](https://img.shields.io/badge/PHP-%E2%89%A5_8.0-777BB4.svg?logo=php&logoColor=white)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-%E2%89%A5_8.0-4479A1.svg?logo=mysql&logoColor=white)](https://www.mysql.com/)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-3.4.11-38B2AC.svg?logo=tailwind-css&logoColor=white)](https://tailwindcss.com/)
[![License](https://img.shields.io/badge/License-Proprietary-red.svg)](#)

OMG (Oh My Gudness) is a luxury bouquet e-commerce platform offering premium flower arrangements, gift hampers, add-on gifts, and bespoke surprise event services. Featuring a premium black-and-gold design aesthetic, flexible delivery options (including scheduled slots and the signature *Midnight Surprise*), and a fully functional management backend, OMG provides a top-tier luxury gifting experience.

---an

## Table of Contents

1. [Features](#features)
2. [Tech Stack](#tech-stack)
3. [Architecture & Folder Structure](#architecture--folder-structure)
4. [Prerequisites](#prerequisites)
5. [Installation & Local Setup](#installation--local-setup)
   - [Database Setup](#1-database-setup)
   - [Backend Configuration](#2-backend-configuration)
   - [Frontend Configuration & Setup](#3-frontend-configuration--setup)
6. [Environment Variables](#environment-variables)
7. [Admin Panel](#admin-panel)
8. [API Endpoints](#api-endpoints)
9. [Troubleshooting & FAQ](#troubleshooting--faq)
10. [License](#license)

---

## Features

### 🛍️ E-Commerce & Product Showcase
* **Curated Luxury Collections**: Oh My Bloom's, Oh My Love's, Oh My Celebration's, Oh My Customisation's, and Oh My Signature's.
* **Database-Driven Product Catalog**: Live search, category filtering, and slug-based routing for detailed product views.
* **Persistent Wishlist**: Synced with database user accounts (using secure UUID relationships).

### 🚚 Advanced Delivery Scheduler
* **Scheduled Delivery Slots**: Choose date and delivery times (Same-Day, Next-Day, or Custom Slots).
* **Midnight Surprise**: Automatically defaults delivery time selection to midnight for surprise deliveries while keeping the date picker active.

### 🔐 Native Authentication
* **OTP Email Verification**: Sign-up flow protected by a 6-digit OTP verification code sent via email.
* **Secure Sessions**: User authentication backed by password hashing (Bcrypt) and secure PHP sessions.

### 💼 Admin & Management Portal
* **Orders Management**: Track, view, and update statuses of customer orders.
* **Bespoke Request Lists**: Manage custom bouquet requests and surprise event inquiries.
* **Admin Account Control**: Create or delete admin users (with root protection for the `main_admin` account).
* **Instant Notifications**: Pre-formatted WhatsApp order details redirecting link generator for order updates.

---

## Tech Stack

| Component | Technology | Version / Details |
| :--- | :--- | :--- |
| **Frontend Core** | React, TypeScript, Vite | React `^18.0.0`, TypeScript `~5.9.3` |
| **Styling** | Tailwind CSS, Radix UI | Premium Black/Gold aesthetic, fluid transitions |
| **State & Fetching** | Axios, React Context | Dynamic cart operations and API hooks |
| **Backend API** | Native PHP | Session-based Auth, PDO (Prepared Statements) |
| **Database** | MySQL / MariaDB | Relational schema with UUID-based IDs |
| **Linter & Formatter**| Biome | Strict code formatting configuration |

---

## Architecture & Folder Structure

The project is structured as a decoupled application with a PHP backend and a Vite+React frontend:

```
├── backend/                    # PHP Backend Application & APIs
│   ├── config.php              # Shared database connection & session setup
│   ├── auth.php                # User auth: login, signup, OTP verify
│   ├── products.php            # Catalog: retrieval, details, search
│   ├── orders.php              # Checkout processing & user order history
│   ├── wishlist.php            # Wishlist tracking (database synced)
│   ├── db_schema.sql           # Database tables & relations definition
│   ├── admin_orders.php        # Admin order panel UI
│   ├── admin_manage.php        # Administrator accounts management
│   ├── admin_inquiries.php     # Bespoke event service requests log
│   └── admin_customisations.php# Custom flower configurations log
├── src/                        # Frontend React Application
│   ├── components/             # Reusable UI & Layout components
│   ├── contexts/               # React Contexts (AuthContext, CartContext)
│   ├── db/                     # Mock data fallback assets
│   ├── hooks/                  # Custom React Hooks
│   ├── pages/                  # Route views (Home, Products, Checkout, etc.)
│   ├── services/               # API service layers (Axios instances)
│   ├── App.tsx                 # Main application shell
│   ├── main.tsx                # Entry point script
│   └── routes.tsx              # Application routing definitions
├── public/                     # Static assets (images, icons)
├── tailwind.config.js          # Tailwind CSS layout and coloring tokens
├── package.json                # Node dependencies & project scripts
└── tsconfig.json               # TypeScript workspace config
```

---

## Prerequisites

Before running the application, make sure you have the following installed on your machine:

* **Node.js** (v20 or higher recommended)
* **npm** (v10 or higher) or **pnpm**
* **PHP** (v8.0 or higher)
* **MySQL** (v8.0 or higher) or **MariaDB**
* Local web server suite such as **XAMPP**, **MAMP**, or the built-in PHP development server.

---

## Installation & Local Setup

### 1. Database Setup
1. Open your MySQL client (e.g., phpMyAdmin, MySQL Workbench, or CLI).
2. Create a new database named `u981836125_OhMyGudness1` (or your preferred local database name):
   ```sql
   CREATE DATABASE u981836125_OhMyGudness1;
   ```
3. Import the database schema from the [db_schema.sql](file:///c:/Users/samrat/Downloads/OMG/backend/db_schema.sql) file:
   ```bash
   mysql -u your_username -p u981836125_OhMyGudness1 < backend/db_schema.sql
   ```

### 2. Backend Configuration
1. Open [backend/config.php](file:///c:/Users/samrat/Downloads/OMG/backend/config.php) and configure your database credentials:
   ```php
   private $host = 'localhost'; // Database host
   private $db_name = 'u981836125_OhMyGudness1';
   private $username = 'YOUR_MYSQL_USER';
   private $password = 'YOUR_MYSQL_PASSWORD';
   ```
2. Start the local PHP development server:
   ```bash
   cd backend
   php -S localhost:8000
   ```
   *(Alternatively, copy the `backend` folder to your local Apache directory like XAMPP's `htdocs/OMG/backend`).*

### 3. Frontend Configuration & Setup
1. Navigate back to the root directory.
2. Create or verify the `.env` file in the project root:
   ```env
   VITE_API_BASE_URL=http://localhost:8000
   ```
3. Install frontend dependencies:
   ```bash
   npm install
   ```
4. Start the frontend development server:
   ```bash
   npm run dev
   ```
5. Open your browser and navigate to `http://localhost:5173`.

---

## Environment Variables

### Frontend (`.env`)
```env
# URL pointing to the active backend folder hosting the PHP scripts
VITE_API_BASE_URL=http://localhost:8000
```

### Backend (`config.php`)
Database credentials and timezones are configured directly in `backend/config.php`. Ensure the allowed CORS origins array (`$allowedOrigins`) contains the address of your running frontend application (e.g., `http://localhost:5173`).

---

## Admin Panel

The administration interface is written in native PHP and is served directly by your PHP server.

* **URL**: `http://localhost:8000/admin_orders.php` (assuming backend is running at port 8000).
* **Restricted Navigation**: Accessible only to authenticated admins.
* **Authentication**: Credentials are saved in the `admin_users` table. The default root user is `main_admin`.
* **Sub-views**:
  - **Orders**: View order logs, check delivery options (Midnight Surprise/Time-slots), and update fulfillment status.
  - **Inquiries**: Review messages sent through the bespoke Surprise Event Planning services.
  - **Customisations**: Read detailed custom flower bouquet request submissions.
  - **Manage Admins**: Root page to register new administrator accounts or delete obsolete credentials (restricted to `main_admin`).

---

## API Endpoints

All actions are requested via query string parameters (`?action=...`):

### Auth APIs (`backend/auth.php`)
* `POST /auth.php?action=register` — Creates user, inserts an empty user profile, and triggers OTP email verification.
* `POST /auth.php?action=login` — Authenticates user, verifies email status, and starts a PHP session.
* `POST /auth.php?action=verify_otp` — Verifies user account using the 6-digit OTP code.
* `POST /auth.php?action=resend_otp` — Resends a fresh OTP code to user's email.
* `GET /auth.php?action=get_user` — Fetches current authenticated session user profile.
* `GET /auth.php?action=logout` — Destroys current backend session.

### Products APIs (`backend/products.php`)
* `GET /products.php?action=get_products&category={cat}` — Fetches list of products (category values include `blooms`, `loves`, `celebrations`, `customisations`, `signatures`).
* `GET /products.php?action=get_product&slug={slug}` — Fetches a single product's details using its unique URL slug.
* `GET /products.php?action=get_featured` — Fetches list of featured products for the home page banner.
* `GET /products.php?action=search&search={term}` — Searches product titles and descriptions for a keyword.

### Orders APIs (`backend/orders.php`)
* `POST /orders.php?action=create_order` — Places a new order and inserts records into `orders` and `order_items`.
* `GET /orders.php?action=get_orders` — Retrieves past orders for the currently authenticated user.
* `GET /orders.php?action=get_order&id={id}` — Retrieves detailed order history by order ID.

### Wishlist APIs (`backend/wishlist.php`)
* `GET /wishlist.php?action=get_wishlist` — Fetches the user's wishlist joined with product details.
* `POST /wishlist.php?action=add` — Adds a product (using its database UUID) to the user's wishlist.
* `GET /wishlist.php?action=remove&product_id={id}` — Removes a product from the user's wishlist.
* `GET /wishlist.php?action=check&product_id={id}` — Checks if a product is currently wishlisted.

---

## Troubleshooting & FAQ

#### 1. Why are email OTPs not sending locally?
The backend calls PHP's native `mail()` function. To send emails from your local system:
- Configure a local SMTP server in your `php.ini` file.
- Alternatively, modify the `sendOTPEmail` function in [backend/auth.php](file:///c:/Users/samrat/Downloads/OMG/backend/auth.php) to use external services like PHPMailer or SendGrid APIs.

#### 2. Why am I getting "Unauthorized" errors after logging in?
Ensure that **CORS** configurations in [backend/config.php](file:///c:/Users/samrat/Downloads/OMG/backend/config.php) are matching your frontend port. The cookie transmission relies on:
- `Access-Control-Allow-Credentials: true`
- Axios instance configured with `withCredentials: true`
- Correct origin listed in `$allowedOrigins` inside `config.php`.

#### 3. I see a SQL error about `prepare` or `bind_param` in `send_order_whatsapp.php`?
Ensure you have successfully imported the `db_schema.sql` database schema and configured the correct variable `$conn` or database configuration variables in config.php.

---

## License

This project is proprietary and confidential. All rights reserved. Authorized distribution and modification are governed strictly under licensing agreements.

---

## Credits & Acknowledgements

* Luxury Bouquet illustrations and graphic design components.
* Fonts used: [Playfair Display](https://fonts.google.com/specimen/Playfair+Display) & [Montserrat](https://fonts.google.com/specimen/Montserrat).
