<?php
// Centralized Admin Header & Session Management
require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 24-Hour Session Lifetime & Inactivity Check (86,400 seconds default, configurable)
$sessionTimeout = getenv('SESSION_TIMEOUT_SECONDS') ? (int)getenv('SESSION_TIMEOUT_SECONDS') : 86400;
if (isset($_SESSION['admin_last_activity']) && (time() - $_SESSION['admin_last_activity'] > $sessionTimeout)) {
    session_unset();
    session_destroy();
    header("Location: admin_orders.php?expired=1");
    exit();
}
$_SESSION['admin_last_activity'] = time();

$isLoggedIn = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
$adminUsername = $_SESSION['admin_username'] ?? 'Admin';
$isMainAdmin = $adminUsername === 'main_admin';

// Determine active page for nav highlighting
$currentPage = basename($_SERVER['PHP_SELF']);
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
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
        .gold-gradient { background: linear-gradient(135deg, #D4AF37 0%, #AA7C11 100%); }
        .glass-header { background: rgba(15, 23, 42, 0.95); backdrop-filter: blur(12px); }
        .table-wrapper { overflow-x: auto; -webkit-overflow-scrolling: touch; }
    </style>
</head>
<body class="font-sans antialiased text-slate-800 bg-slate-50 min-h-full flex flex-col">

<?php if ($isLoggedIn): ?>
    <!-- Admin Top Navbar -->
    <header class="glass-header text-white sticky top-0 z-50 border-b border-slate-800 shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16 md:h-20">
                
                <!-- Logo & Brand -->
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl gold-gradient flex items-center justify-center font-bold font-serif text-slate-950 text-xl shadow-lg">
                        OMG
                    </div>
                    <div>
                        <span class="font-serif font-bold text-lg text-amber-400 tracking-wider">OH MY GUDNESS</span>
                        <span class="hidden sm:inline-block ml-2 px-2 py-0.5 text-[10px] uppercase font-bold tracking-widest bg-amber-400/10 text-amber-400 border border-amber-400/20 rounded">Admin Panel</span>
                    </div>
                </div>

                <!-- Desktop Navigation Links -->
                <nav class="hidden md:flex items-center space-x-1">
                    <a href="admin_orders.php" class="<?php echo $currentPage === 'admin_orders.php' ? 'bg-slate-800 text-amber-400 font-semibold' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white'; ?> px-3 py-2 rounded-lg text-sm transition-all flex items-center gap-1.5">
                        <span>📦</span> Orders
                    </a>
                    <a href="admin_inquiries.php" class="<?php echo $currentPage === 'admin_inquiries.php' ? 'bg-slate-800 text-amber-400 font-semibold' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white'; ?> px-3 py-2 rounded-lg text-sm transition-all flex items-center gap-1.5">
                        <span>💬</span> Inquiries
                    </a>
                    <a href="admin_customisations.php" class="<?php echo $currentPage === 'admin_customisations.php' ? 'bg-slate-800 text-amber-400 font-semibold' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white'; ?> px-3 py-2 rounded-lg text-sm transition-all flex items-center gap-1.5">
                        <span>✨</span> Customisations
                    </a>
                    <a href="admin_surprises.php" class="<?php echo $currentPage === 'admin_surprises.php' ? 'bg-slate-800 text-amber-400 font-semibold' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white'; ?> px-3 py-2 rounded-lg text-sm transition-all flex items-center gap-1.5">
                        <span>🎉</span> Surprises & Pincodes
                    </a>
                    <a href="admin_products.php" class="<?php echo $currentPage === 'admin_products.php' ? 'bg-slate-800 text-amber-400 font-semibold' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white'; ?> px-3 py-2 rounded-lg text-sm transition-all flex items-center gap-1.5">
                        <span>🌸</span> Products
                    </a>
                    <?php if ($isMainAdmin): ?>
                        <a href="admin_manage.php" class="<?php echo $currentPage === 'admin_manage.php' ? 'bg-slate-800 text-amber-400 font-semibold' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white'; ?> px-3 py-2 rounded-lg text-sm transition-all flex items-center gap-1.5">
                            <span>🛡️</span> Admins
                        </a>
                    <?php endif; ?>
                </nav>

                <!-- User & Action Badges -->
                <div class="hidden sm:flex items-center gap-4">
                    <div class="flex items-center gap-2 px-3 py-1.5 rounded-full bg-slate-800/80 border border-slate-700 text-xs">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                        <span class="text-slate-300">Logged as <strong class="text-white"><?php echo htmlspecialchars($adminUsername); ?></strong></span>
                    </div>
                    <a href="?logout=true" class="bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/30 px-3.5 py-1.5 rounded-lg text-xs font-semibold transition-all">
                        Logout
                    </a>
                </div>

                <!-- Mobile Hamburger Button -->
                <div class="flex md:hidden items-center gap-2">
                    <button id="mobile-menu-toggle" class="p-2 text-slate-300 hover:text-white rounded-lg focus:outline-none">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile Menu Drawer -->
        <div id="mobile-menu" class="hidden md:hidden border-t border-slate-800 bg-slate-900 px-4 pt-3 pb-4 space-y-2">
            <div class="px-3 py-2 text-xs text-slate-400 border-b border-slate-800 flex justify-between items-center mb-2">
                <span>Welcome, <strong><?php echo htmlspecialchars($adminUsername); ?></strong></span>
                <span class="text-[10px] bg-emerald-500/20 text-emerald-300 px-2 py-0.5 rounded">24h Session</span>
            </div>
            <a href="admin_orders.php" class="block px-3 py-2.5 rounded-lg text-base font-medium text-slate-200 hover:bg-slate-800 hover:text-amber-400">📦 Orders</a>
            <a href="admin_inquiries.php" class="block px-3 py-2.5 rounded-lg text-base font-medium text-slate-200 hover:bg-slate-800 hover:text-amber-400">💬 Inquiries</a>
            <a href="admin_customisations.php" class="block px-3 py-2.5 rounded-lg text-base font-medium text-slate-200 hover:bg-slate-800 hover:text-amber-400">✨ Customisations</a>
            <a href="admin_surprises.php" class="block px-3 py-2.5 rounded-lg text-base font-medium text-slate-200 hover:bg-slate-800 hover:text-amber-400">🎉 Surprises & Pincodes</a>
            <a href="admin_products.php" class="block px-3 py-2.5 rounded-lg text-base font-medium text-slate-200 hover:bg-slate-800 hover:text-amber-400">🌸 Products</a>
            <?php if ($isMainAdmin): ?>
                <a href="admin_manage.php" class="block px-3 py-2.5 rounded-lg text-base font-medium text-slate-200 hover:bg-slate-800 hover:text-amber-400">🛡️ Manage Admins</a>
            <?php endif; ?>
            <a href="?logout=true" class="block px-3 py-2.5 rounded-lg text-base font-semibold text-rose-400 hover:bg-rose-950/40">Logout</a>
        </div>
    </header>

    <script>
        document.getElementById('mobile-menu-toggle')?.addEventListener('click', function() {
            document.getElementById('mobile-menu')?.classList.toggle('hidden');
        });
    </script>
<?php endif; ?>

<main class="flex-1 py-8 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto w-full">
