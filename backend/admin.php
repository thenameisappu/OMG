<?php
// Primary Entry Point for Admin Portal & Centralized Authentication Guard
require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── 1. AJAX ACTIVITY PING HANDLER ──────────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'ping_activity') {
    header('Content-Type: application/json');
    if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
        $_SESSION['admin_last_activity'] = time();
        echo json_encode(["success" => true, "timestamp" => time()]);
    } else {
        http_response_code(401);
        echo json_encode(["success" => false, "error" => "Unauthenticated"]);
    }
    exit();
}

// ── 2. LOGOUT HANDLER ───────────────────────────────────────────────────────
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

// ── 3. SESSION TIMEOUT & INACTIVITY CHECK (3 Hours = 10,800 seconds) ───────
$sessionTimeout = getenv('SESSION_TIMEOUT_SECONDS') ? (int)getenv('SESSION_TIMEOUT_SECONDS') : (isset($_ENV['SESSION_TIMEOUT_SECONDS']) ? (int)$_ENV['SESSION_TIMEOUT_SECONDS'] : 10800);
$expiredError = "";

if (isset($_GET['expired']) || isset($_SESSION['admin_session_expired'])) {
    $expiredError = "Your session has expired due to inactivity. Please log in again.";
    unset($_SESSION['admin_session_expired']);
}

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
    // Update inactivity timer on active request
    $_SESSION['admin_last_activity'] = time();
}

// ── 4. HANDLE LOGIN POST SUBMISSION ─────────────────────────────────────────
$loginError = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $database = new Database();
    $db = $database->getConnection();

    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $loginError = "Please enter both username and password.";
    } else {
        $query = "SELECT * FROM admin_users WHERE username = :username LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);
            if (password_verify($password, $admin['password'])) {
                // Regenerate session ID to prevent session hijacking/fixation
                session_regenerate_id(true);

                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_last_activity'] = time();
                $_SESSION['is_main_admin'] = (bool)($admin['is_main_admin'] ?? ($admin['username'] === 'main_admin'));

                header("Location: admin.php");
                exit();
            } else {
                $loginError = "Invalid Username or Password.";
            }
        } else {
            $loginError = "Invalid Username or Password.";
        }
    }
}

// ── 5. RENDER UNAUTHENTICATED LOGIN SCREEN ─────────────────────────────────
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true):
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-950">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Portal Login | OH MY GUDNESS</title>
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
                            50: '#FAF6EB', 100: '#F4ECCA', 400: '#D4AF37', 500: '#C5A044', 600: '#A38131',
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
    </style>
</head>
<body class="font-sans antialiased bg-slate-950 text-slate-100 min-h-full flex items-center justify-center p-4 sm:p-6">
    <div class="max-w-md w-full space-y-8 bg-slate-900/90 border border-slate-800 p-8 rounded-3xl shadow-2xl backdrop-blur-md">
        <div class="text-center space-y-4">
            <!-- Brand Logo -->
            <div class="mx-auto flex justify-center">
                <img src="assets/logo.png" alt="OH MY GUDNESS" class="h-16 w-auto object-contain bg-white/95 px-4 py-2 rounded-2xl border border-amber-400/40 shadow-xl" onerror="this.onerror=null; this.src='../images/logo/omg-brand-logo.png';">
            </div>
            <div>
                <h2 class="text-2xl sm:text-3xl font-serif font-bold text-amber-400 tracking-wide">Admin Portal Access</h2>
                <p class="mt-1 text-xs text-slate-400">Sign in to manage orders, products, inquiries & configurations</p>
            </div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-slate-800 border border-slate-700 text-[11px] font-semibold text-slate-300">
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                <span>⏱️ 3-Hour Active Session Protection</span>
            </div>
        </div>

        <?php if (!empty($expiredError)): ?>
            <div class="bg-amber-500/10 border border-amber-500/30 text-amber-300 px-4 py-3 rounded-2xl text-xs font-medium flex items-center gap-2">
                <span class="text-base">⚠️</span> <span><?php echo htmlspecialchars($expiredError); ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($loginError)): ?>
            <div class="bg-rose-500/10 border border-rose-500/30 text-rose-300 px-4 py-3 rounded-2xl text-xs font-medium flex items-center gap-2">
                <span class="text-base">❌</span> <span><?php echo htmlspecialchars($loginError); ?></span>
            </div>
        <?php endif; ?>

        <form class="space-y-5" method="POST" action="admin.php">
            <input type="hidden" name="action" value="login">
            <div class="space-y-1">
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-400">Administrator Username</label>
                <input type="text" name="username" required autofocus placeholder="Enter username" class="w-full px-4 py-3 rounded-xl bg-slate-950 border border-slate-800 focus:border-amber-500 focus:ring-2 focus:ring-amber-500/30 text-sm text-slate-100 transition-all outline-none">
            </div>
            <div class="space-y-1">
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-400">Security Password</label>
                <input type="password" name="password" required placeholder="••••••••" class="w-full px-4 py-3 rounded-xl bg-slate-950 border border-slate-800 focus:border-amber-500 focus:ring-2 focus:ring-amber-500/30 text-sm text-slate-100 transition-all outline-none">
            </div>

            <button type="submit" class="w-full py-3.5 px-4 rounded-xl text-slate-950 font-bold gold-gradient shadow-lg hover:shadow-amber-500/20 hover:scale-[1.01] transition-all text-sm tracking-wide">
                Sign In to Portal
            </button>
        </form>

        <div class="text-center text-[11px] text-slate-500 border-t border-slate-800/80 pt-4">
            © <?php echo date('Y'); ?> <strong>OH MY GUDNESS</strong> Administrative Security System
        </div>
    </div>
</body>
</html>
<?php
exit();
endif;

// ── 6. RENDER DASHBOARD TABS FOR AUTHENTICATED ADMINS ──────────────────────
$tab = $_GET['tab'] ?? 'orders';

switch ($tab) {
    case 'inquiries':
        require_once 'admin_inquiries.php';
        break;
    case 'customisations':
        require_once 'admin_customisations.php';
        break;
    case 'surprises':
        require_once 'admin_surprises.php';
        break;
    case 'products':
        require_once 'admin_products.php';
        break;
    case 'manage':
        require_once 'admin_manage.php';
        break;
    case 'orders':
    default:
        require_once 'admin_orders.php';
        break;
}
?>
