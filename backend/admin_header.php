<?php
// Centralized Admin Header, Security Guard & Session Lifetime Manager
require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── 1. CENTRALIZED LOGOUT HANDLER ──────────────────────────────────────────
if (isset($_GET['logout'])) {
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    session_unset();
    session_destroy();
    header("Location: admin.php");
    exit();
}

// ── 2. SESSION TIMEOUT & INACTIVITY CHECK (3 Hours = 10,800 seconds) ───────
$sessionTimeout = getenv('SESSION_TIMEOUT_SECONDS') ? (int) getenv('SESSION_TIMEOUT_SECONDS') : (isset($_ENV['SESSION_TIMEOUT_SECONDS']) ? (int) $_ENV['SESSION_TIMEOUT_SECONDS'] : 10800);

if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    if (isset($_SESSION['admin_last_activity']) && (time() - $_SESSION['admin_last_activity'] > $sessionTimeout)) {
        $_SESSION = array();
        session_unset();
        session_destroy();
        session_start();
        $_SESSION['admin_session_expired'] = true;
        header("Location: admin.php?expired=1");
        exit();
    }
    $_SESSION['admin_last_activity'] = time();
}

$isLoggedIn = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
$adminUsername = $_SESSION['admin_username'] ?? 'Admin';
$isMainAdmin = $adminUsername === 'main_admin';

// Determine active page & active tab for highlighting
$currentPage = basename($_SERVER['PHP_SELF']);
$currentTab = $_GET['tab'] ?? '';
if (empty($currentTab)) {
    if (strpos($currentPage, 'orders') !== false)
        $currentTab = 'orders';
    elseif (strpos($currentPage, 'inquiries') !== false)
        $currentTab = 'inquiries';
    elseif (strpos($currentPage, 'customisations') !== false)
        $currentTab = 'customisations';
    elseif (strpos($currentPage, 'surprises') !== false)
        $currentTab = 'surprises';
    elseif (strpos($currentPage, 'products') !== false)
        $currentTab = 'products';
    elseif (strpos($currentPage, 'manage') !== false)
        $currentTab = 'manage';
    else
        $currentTab = 'orders';
}

// ── 3. AUTHENTICATION GUARD ───────────────────────────────────────────────
// If user tries to access any admin page without logging in, redirect to /backend/admin.php
if (!$isLoggedIn && $currentPage !== 'admin.php') {
    header("Location: admin.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) . ' | OMG Admin' : 'OMG Admin Portal'; ?></title>

    <!-- Google Fonts & Tailwind CDN -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@600;700&display=swap"
        rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        gold: {
                            50: '#FAF6EB',
                            100: '#F4ECCA',
                            200: '#E8D795',
                            400: '#D4AF37',
                            500: '#C5A044',
                            600: '#A38131',
                        },
                        navy: {
                            900: '#0F172A',
                            950: '#090D16',
                        }
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                        serif: ['Playfair Display', 'serif'],
                    }
                }
            }
        }
    </script>
    <style>
        .gold-gradient {
            background: linear-gradient(135deg, #D4AF37 0%, #AA7C11 100%);
        }

        .glass-header {
            background: rgba(15, 23, 42, 0.95);
            backdrop-filter: blur(12px);
        }

        .table-wrapper {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .prod-thumb {
            width: 52px !important;
            height: 52px !important;
            min-width: 52px !important;
            min-height: 52px !important;
            max-width: 52px !important;
            max-height: 52px !important;
            object-fit: cover !important;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
        }
    </style>
</head>

<body class="font-sans antialiased text-slate-800 bg-slate-50 min-h-full flex flex-col">

    <?php if ($isLoggedIn): ?>
        <!-- Admin Top Navbar -->
        <header class="glass-header text-white sticky top-0 z-50 border-b border-slate-800 shadow-md">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between h-16 md:h-20">

                    <!-- Official Brand Logo -->
                    <div class="flex items-center gap-3">
                        <a href="admin.php" class="flex items-center gap-3 group">
                            <img src="assets/logo.png" alt="OH MY GUDNESS"
                                class="h-10 w-auto object-contain bg-white/95 px-2.5 py-1 rounded-xl border border-amber-400/40 shadow-md group-hover:scale-105 transition-transform"
                                onerror="this.onerror=null; this.src='../images/logo/omg-brand-logo.png';">
                            <div>
                                <span class="font-serif font-bold text-base sm:text-lg text-amber-400 tracking-wider">OH MY
                                    GUDNESS</span>
                                <span
                                    class="hidden sm:inline-block ml-2 px-2 py-0.5 text-[10px] uppercase font-bold tracking-widest bg-amber-400/10 text-amber-400 border border-amber-400/20 rounded">Admin
                                    Panel</span>
                            </div>
                        </a>
                    </div>

                    <!-- Desktop Navigation Links -->
                    <nav class="hidden md:flex items-center space-x-1">
                        <a href="admin.php?tab=orders"
                            class="<?php echo $currentTab === 'orders' ? 'bg-slate-800 text-amber-400 font-semibold border border-amber-400/20' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white'; ?> px-3 py-2 rounded-lg text-sm transition-all flex items-center gap-1.5">
                            <span>📦</span> Orders
                        </a>
                        <a href="admin.php?tab=inquiries"
                            class="<?php echo $currentTab === 'inquiries' ? 'bg-slate-800 text-amber-400 font-semibold border border-amber-400/20' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white'; ?> px-3 py-2 rounded-lg text-sm transition-all flex items-center gap-1.5">Inquiries</a>
                        <a href="admin.php?tab=customisations"
                            class="<?php echo $currentTab === 'customisations' ? 'bg-slate-800 text-amber-400 font-semibold border border-amber-400/20' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white'; ?> px-3 py-2 rounded-lg text-sm transition-all flex items-center gap-1.5">Customisations</a>
                        <a href="admin.php?tab=surprises"
                            class="<?php echo $currentTab === 'surprises' ? 'bg-slate-800 text-amber-400 font-semibold border border-amber-400/20' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white'; ?> px-3 py-2 rounded-lg text-sm transition-all flex items-center gap-1.5">Surprises
                            & Pincodes</a>
                        <a href="admin.php?tab=products"
                            class="<?php echo $currentTab === 'products' ? 'bg-slate-800 text-amber-400 font-semibold border border-amber-400/20' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white'; ?> px-3 py-2 rounded-lg text-sm transition-all flex items-center gap-1.5">Products</a>
                        <?php if ($isMainAdmin): ?>
                            <a href="admin.php?tab=manage"
                                class="<?php echo $currentTab === 'manage' ? 'bg-slate-800 text-amber-400 font-semibold border border-amber-400/20' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white'; ?> px-3 py-2 rounded-lg text-sm transition-all flex items-center gap-1.5">
                                <span>🛡️</span> Admins
                            </a>
                        <?php endif; ?>
                    </nav>

                    <!-- User Status & Logout -->
                    <div class="hidden sm:flex items-center gap-4">
                        <div
                            class="flex items-center gap-2 px-3 py-1.5 rounded-full bg-slate-800/80 border border-slate-700 text-xs">
                            <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                            <span class="text-slate-300">Logged as <strong
                                    class="text-white"><?php echo htmlspecialchars($adminUsername); ?></strong></span>
                        </div>
                        <a href="admin.php?logout=1"
                            class="bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/30 px-3.5 py-1.5 rounded-lg text-xs font-semibold transition-all">
                            Logout
                        </a>
                    </div>

                    <!-- Mobile Hamburger Button -->
                    <div class="flex md:hidden items-center gap-2">
                        <button id="mobile-menu-toggle"
                            class="p-2 text-slate-300 hover:text-white rounded-lg focus:outline-none">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 6h16M4 12h16M4 18h16"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Mobile Menu Drawer -->
            <div id="mobile-menu" class="hidden md:hidden border-t border-slate-800 bg-slate-900 px-4 pt-3 pb-4 space-y-2">
                <div
                    class="px-3 py-2 text-xs text-slate-400 border-b border-slate-800 flex justify-between items-center mb-2">
                    <span>Welcome, <strong><?php echo htmlspecialchars($adminUsername); ?></strong></span>
                    <span class="text-[10px] bg-emerald-500/20 text-emerald-300 px-2 py-0.5 rounded">3h Active
                        Session</span>
                </div>
                <a href="admin.php?tab=orders"
                    class="block px-3 py-2.5 rounded-lg text-base font-medium text-slate-200 hover:bg-slate-800 hover:text-amber-400">📦
                    Orders</a>
                <a href="admin.php?tab=inquiries"
                    class="block px-3 py-2.5 rounded-lg text-base font-medium text-slate-200 hover:bg-slate-800 hover:text-amber-400">💬
                    Inquiries</a>
                <a href="admin.php?tab=customisations"
                    class="block px-3 py-2.5 rounded-lg text-base font-medium text-slate-200 hover:bg-slate-800 hover:text-amber-400">✨
                    Customisations</a>
                <a href="admin.php?tab=surprises"
                    class="block px-3 py-2.5 rounded-lg text-base font-medium text-slate-200 hover:bg-slate-800 hover:text-amber-400">🎉
                    Surprises & Pincodes</a>
                <a href="admin.php?tab=products"
                    class="block px-3 py-2.5 rounded-lg text-base font-medium text-slate-200 hover:bg-slate-800 hover:text-amber-400">🌸
                    Products</a>
                <?php if ($isMainAdmin): ?>
                    <a href="admin.php?tab=manage"
                        class="block px-3 py-2.5 rounded-lg text-base font-medium text-slate-200 hover:bg-slate-800 hover:text-amber-400">🛡️
                        Manage Admins</a>
                <?php endif; ?>
                <a href="admin.php?logout=1"
                    class="block px-3 py-2.5 rounded-lg text-base font-semibold text-rose-400 hover:bg-rose-950/40">Logout</a>
            </div>
        </header>

        <script>
            document.getElementById('mobile-menu-toggle')?.addEventListener('click', function () {
                document.getElementById('mobile-menu')?.classList.toggle('hidden');
            });

            // Client-side activity listener to send background activity ping every 4 minutes while user is active
            (function () {
                let active = false;
                const setActivity = () => { active = true; };
                ['mousemove', 'keydown', 'click', 'scroll', 'touchstart'].forEach(evt => {
                    window.addEventListener(evt, setActivity, { passive: true });
                });

                setInterval(() => {
                    if (active) {
                        active = false;
                        fetch('admin.php?action=ping_activity').catch(() => { });
                    }
                }, 240000); // 4 minutes
            })();
        </script>
    <?php endif; ?>

    <main class="flex-1 py-8 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto w-full">