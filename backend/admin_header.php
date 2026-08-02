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
$adminDisplayName = $adminUsername;
$adminEmail = '';
$adminId = '';
$isMainAdmin = ($adminUsername === 'main_admin');
$allAdminsList = [];

if (isset($db) && $db !== null && $isLoggedIn) {
    try {
        $headerUserStmt = $db->prepare("SELECT * FROM admin_users WHERE username = :u LIMIT 1");
        $headerUserStmt->execute([':u' => $adminUsername]);
        $headerUser = $headerUserStmt->fetch(PDO::FETCH_ASSOC);
        if ($headerUser) {
            $adminId = $headerUser['id'];
            $adminDisplayName = !empty($headerUser['name']) ? $headerUser['name'] : $headerUser['username'];
            $adminEmail = $headerUser['email'] ?? '';
            $isMainAdmin = (bool)($headerUser['is_main_admin'] ?? ($headerUser['username'] === 'main_admin'));
        }

        $allAdminsStmt = $db->query("SELECT id, username, name, email, is_main_admin, created_at FROM admin_users ORDER BY username ASC");
        if ($allAdminsStmt) {
            $allAdminsList = $allAdminsStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {}
}

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
if (!$isLoggedIn && $currentPage !== 'admin.php') {
    header("Location: admin.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-100">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) . ' | OH MY GUDNESS Admin' : 'Admin Portal | OH MY GUDNESS'; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        gold: { 50: '#FAF6EB', 100: '#F4ECCA', 400: '#D4AF37', 500: '#C5A044', 600: '#A38131' }
                    },
                    fontFamily: { sans: ['Inter', 'sans-serif'], serif: ['Playfair Display', 'serif'] }
                }
            }
        }
    </script>
    <style>
        .gold-gradient { background: linear-gradient(135deg, #D4AF37 0%, #AA7C11 100%); }
        .scrollbar-hide::-webkit-scrollbar { display: none; }
        .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
        .table-wrapper { overflow-x: auto; width: 100%; -webkit-overflow-scrolling: touch; }
    </style>
</head>

<body class="font-sans antialiased bg-slate-100 text-slate-800 min-h-full flex flex-col">
    <?php if ($isLoggedIn): ?>
        <!-- Primary Admin Navigation Header -->
        <header class="bg-slate-950 border-b border-slate-800 text-white sticky top-0 z-40 shadow-md">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between h-16">
                    <!-- Brand & Portal Tag -->
                    <div class="flex items-center gap-3">
                        <a href="admin.php" class="flex items-center gap-2">
                            <img src="assets/logo.png" alt="OMG Logo" class="h-8 w-auto object-contain bg-white/90 px-2 py-1 rounded-lg border border-amber-400/40" onerror="this.onerror=null; this.src='../images/logo/omg-brand-logo.png';">
                            <span class="font-serif font-bold text-lg text-amber-400 tracking-wider hidden xs:inline">OH MY GUDNESS</span>
                        </a>
                        <span class="px-2 py-0.5 text-[10px] font-bold uppercase tracking-widest bg-amber-500/20 text-amber-400 border border-amber-500/30 rounded-md">
                            Admin Panel
                        </span>
                    </div>

                    <!-- Navigation Tabs -->
                    <nav class="hidden md:flex items-center gap-1.5">
                        <a href="admin.php?tab=orders"
                            class="<?php echo $currentTab === 'orders' ? 'bg-slate-800 text-amber-400 font-semibold border border-amber-400/20' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white'; ?> px-3 py-2 rounded-lg text-sm transition-all flex items-center gap-1.5">Orders</a>
                        <a href="admin.php?tab=inquiries"
                            class="<?php echo $currentTab === 'inquiries' ? 'bg-slate-800 text-amber-400 font-semibold border border-amber-400/20' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white'; ?> px-3 py-2 rounded-lg text-sm transition-all flex items-center gap-1.5">Inquiries</a>
                        <a href="admin.php?tab=customisations"
                            class="<?php echo $currentTab === 'customisations' ? 'bg-slate-800 text-amber-400 font-semibold border border-amber-400/20' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white'; ?> px-3 py-2 rounded-lg text-sm transition-all flex items-center gap-1.5">Customisations</a>
                        <a href="admin.php?tab=surprises"
                            class="<?php echo $currentTab === 'surprises' ? 'bg-slate-800 text-amber-400 font-semibold border border-amber-400/20' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white'; ?> px-3 py-2 rounded-lg text-sm transition-all flex items-center gap-1.5">Surprises</a>
                        <a href="admin.php?tab=products"
                            class="<?php echo $currentTab === 'products' ? 'bg-slate-800 text-amber-400 font-semibold border border-amber-400/20' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white'; ?> px-3 py-2 rounded-lg text-sm transition-all flex items-center gap-1.5">Products</a>
                        <?php if ($isMainAdmin): ?>
                            <a href="admin.php?tab=manage"
                                class="<?php echo $currentTab === 'manage' ? 'bg-slate-800 text-amber-400 font-semibold border border-amber-400/20' : 'text-slate-300 hover:bg-slate-800/60 hover:text-white'; ?> px-3 py-2 rounded-lg text-sm transition-all flex items-center gap-1.5">
                                <span>🛡️</span> Admins
                            </a>
                        <?php endif; ?>
                    </nav>

                    <!-- User Status Button & Logout -->
                    <div class="hidden sm:flex items-center gap-4">
                        <button type="button" onclick="openAdminHeaderProfileModal()"
                            class="flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-slate-800/90 hover:bg-slate-700/90 border border-slate-700 hover:border-amber-400/50 text-xs transition-all cursor-pointer shadow-sm group">
                            <span class="text-slate-300">Logged in as <strong class="text-amber-400 group-hover:text-amber-300"><?php echo htmlspecialchars($adminDisplayName); ?></strong></span>
                            <span class="text-[10px] text-slate-400">▼</span>
                        </button>
                        <a href="admin.php?logout=1"
                            class="bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/30 px-3.5 py-1.5 rounded-lg text-xs font-semibold transition-all">
                            Logout
                        </a>
                    </div>
                </div>
            </div>

            <!-- Mobile Menu Drawer -->
            <div id="mobile-menu" class="hidden md:hidden border-t border-slate-800 bg-slate-900 px-4 pt-3 pb-4 space-y-2">
                <div class="px-3 py-2 text-xs text-slate-400 border-b border-slate-800 flex justify-between items-center mb-2">
                    <span>Logged in as: <button type="button" onclick="openAdminHeaderProfileModal()" class="text-amber-400 underline font-bold"><?php echo htmlspecialchars($adminDisplayName); ?></button></span>
                </div>
                <a href="admin.php?tab=orders"
                    class="block px-3 py-2.5 rounded-lg text-base font-medium text-slate-200 hover:bg-slate-800 hover:text-amber-400">📦 Orders</a>
                <a href="admin.php?tab=inquiries"
                    class="block px-3 py-2.5 rounded-lg text-base font-medium text-slate-200 hover:bg-slate-800 hover:text-amber-400">💬 Inquiries</a>
                <a href="admin.php?tab=customisations"
                    class="block px-3 py-2.5 rounded-lg text-base font-medium text-slate-200 hover:bg-slate-800 hover:text-amber-400">✨ Customisations</a>
                <a href="admin.php?tab=surprises"
                    class="block px-3 py-2.5 rounded-lg text-base font-medium text-slate-200 hover:bg-slate-800 hover:text-amber-400">🎉 Surprises & Pincodes</a>
                <a href="admin.php?tab=products"
                    class="block px-3 py-2.5 rounded-lg text-base font-medium text-slate-200 hover:bg-slate-800 hover:text-amber-400">🌸 Products</a>
                <?php if ($isMainAdmin): ?>
                    <a href="admin.php?tab=manage"
                        class="block px-3 py-2.5 rounded-lg text-base font-medium text-slate-200 hover:bg-slate-800 hover:text-amber-400">🛡️ Manage Admins</a>
                <?php endif; ?>
                <a href="admin.php?logout=1"
                    class="block px-3 py-2.5 rounded-lg text-base font-semibold text-rose-400 hover:bg-rose-950/40">Logout</a>
            </div>
        </header>

        <!-- GLOBAL ADMIN PROFILE & ACCOUNTS MODAL (TRIGGERED BY LOGGED IN AS BUTTON) -->
        <div id="adminHeaderProfileModal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-md hidden items-center justify-center p-4">
            <div class="bg-white border border-slate-200 max-w-3xl w-full rounded-3xl p-6 shadow-2xl space-y-6 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-amber-100 text-amber-800 border border-amber-300 flex items-center justify-center font-serif font-bold text-lg">
                            <?php echo htmlspecialchars(strtoupper(substr($adminDisplayName, 0, 1))); ?>
                        </div>
                        <div>
                            <h3 class="text-lg font-serif font-bold text-slate-900">
                                Logged in as: <?php echo htmlspecialchars($adminDisplayName); ?>
                            </h3>
                            <p class="text-xs text-slate-500">
                                Admin ID: <strong class="text-slate-800"><?php echo htmlspecialchars($adminUsername); ?></strong> &bull; 
                                Role: <span class="font-bold text-amber-700"><?php echo $isMainAdmin ? 'Root Main Admin' : 'Administrator'; ?></span>
                            </p>
                        </div>
                    </div>
                    <button type="button" onclick="closeAdminHeaderProfileModal()" class="text-slate-400 hover:text-slate-600 font-bold text-2xl leading-none">&times;</button>
                </div>

                <!-- SECTION 1: MY ADMIN PROFILE & SECURITY SETTINGS (MAIN ADMIN ONLY) -->
                <div class="space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                        <h4 class="text-sm font-bold uppercase tracking-wider text-slate-800 flex items-center gap-2">
                            👤 My Admin Profile & Security Settings
                        </h4>
                        <?php if ($isMainAdmin): ?>
                            <span class="text-[11px] font-bold text-amber-700 bg-amber-50 px-2.5 py-0.5 rounded-full border border-amber-200">Main Admin Granted</span>
                        <?php else: ?>
                            <span class="text-[11px] font-bold text-rose-600 bg-rose-50 px-2.5 py-0.5 rounded-full border border-rose-200">Restricted to Main Admin</span>
                        <?php endif; ?>
                    </div>

                    <?php if ($isMainAdmin): ?>
                        <form method="POST" action="admin.php?tab=manage" class="space-y-4">
                            <input type="hidden" name="action" value="update_my_profile">
                            
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1">Admin ID (Username)</label>
                                    <input type="text" value="<?php echo htmlspecialchars($adminUsername); ?>" disabled
                                        class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-100 text-slate-500 text-sm font-semibold cursor-not-allowed">
                                    <span class="text-[11px] text-slate-400 mt-1 block">Admin ID cannot be changed.</span>
                                </div>

                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1">Display Name</label>
                                    <input type="text" name="my_name" placeholder="Full Display Name" value="<?php echo htmlspecialchars($adminDisplayName); ?>"
                                        class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-amber-500 outline-none">
                                    <span class="text-[11px] text-slate-400 mt-1 block">Admin ID & Display Name can be different.</span>
                                </div>

                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1">Admin Email Address</label>
                                    <input type="email" name="my_email" required placeholder="admin@ohmygudness.in" value="<?php echo htmlspecialchars($adminEmail); ?>"
                                        class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-amber-500 outline-none">
                                    <span class="text-[11px] text-slate-400 mt-1 block">Used for OTP password resets.</span>
                                </div>
                            </div>

                            <div class="pt-3 border-t border-slate-100 grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1">Current Password *</label>
                                    <input type="password" name="current_password" required placeholder="••••••••"
                                        class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-amber-500 outline-none">
                                    <span class="text-[11px] text-slate-400 mt-1 block">Required to confirm profile changes.</span>
                                </div>

                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1">New Password (Optional)</label>
                                    <input type="password" name="new_password" placeholder="Leave blank to keep current"
                                        class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-amber-500 outline-none">
                                </div>

                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1">Confirm New Password</label>
                                    <input type="password" name="confirm_password" placeholder="Confirm new password"
                                        class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-amber-500 outline-none">
                                </div>
                            </div>

                            <div class="flex justify-end pt-2">
                                <button type="submit" class="py-2.5 px-6 gold-gradient text-slate-950 font-bold rounded-xl text-sm shadow-md hover:shadow-lg transition-all">
                                    Save Profile & Password Updates
                                </button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-600">
                            ℹ️ <strong>Access Restricted:</strong> Profile & Password Security Settings are restricted to the Main Admin. Non-main admins cannot edit account settings.
                        </div>
                    <?php endif; ?>
                </div>

                <!-- SECTION 2: ALL ADMINISTRATOR ACCOUNTS DIRECTORY -->
                <div class="space-y-3 pt-4 border-t border-slate-100">
                    <h4 class="text-sm font-bold uppercase tracking-wider text-slate-800 flex items-center justify-between">
                        <span>📋 All Administrator Accounts (<?php echo count($allAdminsList); ?>)</span>
                    </h4>
                    
                    <div class="border border-slate-200 rounded-2xl overflow-hidden">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-50 text-slate-600 uppercase text-[10px] font-bold tracking-wider border-b border-slate-200">
                                <tr>
                                    <th class="py-2.5 px-3">Display Name</th>
                                    <th class="py-2.5 px-3">Username (Admin ID)</th>
                                    <th class="py-2.5 px-3">Email Address</th>
                                    <th class="py-2.5 px-3">Role</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php foreach ($allAdminsList as $adm): ?>
                                    <tr class="<?php echo $adm['username'] === $adminUsername ? 'bg-amber-50/50 font-semibold' : 'hover:bg-slate-50'; ?>">
                                        <td class="py-3 px-3 text-slate-900 font-bold">
                                            <?php echo htmlspecialchars($adm['name'] ?: $adm['username']); ?>
                                        </td>
                                        <td class="py-3 px-3 text-slate-600 font-mono">
                                            <?php echo htmlspecialchars($adm['username']); ?>
                                        </td>
                                        <td class="py-3 px-3 text-slate-600">
                                            <?php echo htmlspecialchars($adm['email'] ?: 'Not set'); ?>
                                        </td>
                                        <td class="py-3 px-3">
                                            <?php if ($adm['username'] === 'main_admin' || !empty($adm['is_main_admin'])): ?>
                                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-300">Root Admin</span>
                                            <?php else: ?>
                                                <span class="px-2 py-0.5 rounded text-[10px] font-medium bg-slate-100 text-slate-700">Administrator</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <script>
            document.getElementById('mobile-menu-toggle')?.addEventListener('click', function () {
                document.getElementById('mobile-menu')?.classList.toggle('hidden');
            });

            function openAdminHeaderProfileModal() {
                document.getElementById('adminHeaderProfileModal').classList.remove('hidden');
                document.getElementById('adminHeaderProfileModal').classList.add('flex');
            }
            function closeAdminHeaderProfileModal() {
                document.getElementById('adminHeaderProfileModal').classList.add('hidden');
                document.getElementById('adminHeaderProfileModal').classList.remove('flex');
            }

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