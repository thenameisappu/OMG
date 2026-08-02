<?php
// Primary Entry Point for Admin Portal & Centralized Authentication Guard
require_once 'config.php';
require_once 'mailer.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$database = new Database();
$db = $database->getConnection();
ensureAuthTablesExist($db);

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

// ── 2. ADMIN FORGOT PASSWORD AJAX HANDLERS ─────────────────────────────────
if (isset($_GET['action']) && in_array($_GET['action'], ['admin_request_otp', 'admin_verify_otp', 'admin_reset_pass'])) {
    header('Content-Type: application/json');
    $inputData = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $subAction = $_GET['action'];

    try {
        if ($subAction === 'admin_request_otp') {
            $identifier = trim($inputData['identifier'] ?? '');
            if (empty($identifier)) {
                throw new Exception("Please enter your registered admin username or email address.");
            }

            $cooldownKey = 'admin_reset_cooldown_' . md5($identifier);
            if (isset($_SESSION[$cooldownKey]) && (time() - $_SESSION[$cooldownKey]) < 60) {
                $rem = 60 - (time() - $_SESSION[$cooldownKey]);
                throw new Exception("Please wait {$rem} seconds before requesting another code.");
            }

            $stmt = $db->prepare("SELECT id, username, email FROM admin_users WHERE LOWER(username) = LOWER(:id) OR LOWER(email) = LOWER(:id) LIMIT 1");
            $stmt->execute([':id' => $identifier]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$admin) {
                throw new Exception("No administrator account found matching your input.");
            }

            $targetEmail = !empty($admin['email']) ? $admin['email'] : 'admin@ohmygudness.in';
            $otp = sprintf("%06d", mt_rand(0, 999999));
            $expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));

            $update = $db->prepare("UPDATE admin_users SET otp_code = :otp, otp_expiry = :expiry WHERE id = :id");
            $update->execute([':otp' => $otp, ':expiry' => $expiry, ':id' => $admin['id']]);

            $_SESSION[$cooldownKey] = time();
            $_SESSION['admin_reset_id'] = $admin['id'];

            $htmlBody = buildForgotPasswordTemplate($admin['username'], $otp);
            sendEmail($targetEmail, "Admin Password Reset OTP - OH MY GUDNESS", $htmlBody);

            echo json_encode([
                "success" => true,
                "message" => "A 6-digit OTP code has been sent to " . htmlspecialchars($targetEmail) . "."
            ]);
            exit();

        } elseif ($subAction === 'admin_verify_otp') {
            $identifier = trim($inputData['identifier'] ?? '');
            $otp = trim($inputData['otp'] ?? '');

            if (empty($identifier) || empty($otp)) {
                throw new Exception("Identifier and OTP code are required.");
            }

            $stmt = $db->prepare("SELECT id, otp_code, otp_expiry FROM admin_users WHERE LOWER(username) = LOWER(:id) OR LOWER(email) = LOWER(:id) LIMIT 1");
            $stmt->execute([':id' => $identifier]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$admin) {
                throw new Exception("Admin account not found.");
            }

            $now = date('Y-m-d H:i:s');
            if ($admin['otp_code'] === $otp && $admin['otp_expiry'] >= $now) {
                $_SESSION['admin_reset_verified_' . $admin['id']] = true;
                echo json_encode(["success" => true, "message" => "OTP verified! Please set your new password."]);
            } else {
                throw new Exception("Invalid or expired OTP code.");
            }
            exit();

        } elseif ($subAction === 'admin_reset_pass') {
            $identifier = trim($inputData['identifier'] ?? '');
            $otp = trim($inputData['otp'] ?? '');
            $newPassword = trim($inputData['new_password'] ?? '');

            if (empty($identifier) || empty($otp) || empty($newPassword)) {
                throw new Exception("All fields are required.");
            }

            if (strlen($newPassword) < 6) {
                throw new Exception("Password must be at least 6 characters long.");
            }

            $stmt = $db->prepare("SELECT id, otp_code, otp_expiry FROM admin_users WHERE LOWER(username) = LOWER(:id) OR LOWER(email) = LOWER(:id) LIMIT 1");
            $stmt->execute([':id' => $identifier]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$admin) {
                throw new Exception("Admin account not found.");
            }

            $now = date('Y-m-d H:i:s');
            if ($admin['otp_code'] === $otp && $admin['otp_expiry'] >= $now) {
                $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
                $update = $db->prepare("UPDATE admin_users SET password = :hash, otp_code = NULL, otp_expiry = NULL WHERE id = :id");
                $update->execute([':hash' => $hashed, ':id' => $admin['id']]);

                unset($_SESSION['admin_reset_verified_' . $admin['id']]);
                echo json_encode(["success" => true, "message" => "Admin password reset successfully! You can now sign in with your new password."]);
            } else {
                throw new Exception("Invalid or expired OTP code.");
            }
            exit();
        }
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => $e->getMessage()]);
        exit();
    }
}

// ── 3. LOGOUT HANDLER ───────────────────────────────────────────────────────
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

// ── 4. SESSION TIMEOUT & INACTIVITY CHECK (3 Hours = 10,800 seconds) ───────
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
    $_SESSION['admin_last_activity'] = time();
}

// ── 5. HANDLE LOGIN POST SUBMISSION ─────────────────────────────────────────
$loginError = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
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

// ── 6. RENDER UNAUTHENTICATED LOGIN SCREEN ─────────────────────────────────
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
                        gold: { 50: '#FAF6EB', 100: '#F4ECCA', 400: '#D4AF37', 500: '#C5A044', 600: '#A38131' }
                    },
                    fontFamily: { sans: ['Inter', 'sans-serif'], serif: ['Playfair Display', 'serif'] }
                }
            }
        }
    </script>
    <style>
        .gold-gradient { background: linear-gradient(135deg, #D4AF37 0%, #AA7C11 100%); }
    </style>
</head>
<body class="font-sans antialiased bg-slate-950 text-slate-100 min-h-full flex items-center justify-center p-4 sm:p-6">
    <div class="max-w-md w-full space-y-8 bg-slate-900/90 border border-slate-800 p-8 rounded-3xl shadow-2xl backdrop-blur-md relative">
        <div class="text-center space-y-4">
            <!-- Brand Logo -->
            <div class="mx-auto flex justify-center">
                <img src="assets/logo.png" alt="OH MY GUDNESS" class="h-16 w-auto object-contain bg-white/95 px-4 py-2 rounded-2xl border border-amber-400/40 shadow-xl" onerror="this.onerror=null; this.src='../images/logo/omg-brand-logo.png';">
            </div>
            <div>
                <h2 class="text-2xl sm:text-3xl font-serif font-bold text-amber-400 tracking-wide">Admin Portal Access</h2>
                <p class="mt-1 text-xs text-slate-400">Sign in to manage orders, products, inquiries & configurations</p>
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

        <!-- Admin Login Form -->
        <form class="space-y-5" method="POST" action="admin.php">
            <input type="hidden" name="action" value="login">
            <div class="space-y-1">
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-400">Administrator Username</label>
                <input type="text" name="username" required autofocus placeholder="Enter username" class="w-full px-4 py-3 rounded-xl bg-slate-950 border border-slate-800 focus:border-amber-500 focus:ring-2 focus:ring-amber-500/30 text-sm text-slate-100 transition-all outline-none">
            </div>
            <div class="space-y-1">
                <div class="flex items-center justify-between">
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-400">Security Password</label>
                    <button type="button" onclick="openAdminResetModal()" class="text-xs text-amber-400 hover:text-amber-300 hover:underline">Forgot Password?</button>
                </div>
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

    <!-- ADMIN FORGOT PASSWORD MODAL -->
    <div id="adminResetModal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-md hidden items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-800 max-w-md w-full rounded-3xl p-6 shadow-2xl space-y-6">
            <div class="flex items-center justify-between border-b border-slate-800 pb-4">
                <div class="flex items-center gap-2">
                    <span class="w-8 h-8 rounded-xl gold-gradient flex items-center justify-center text-slate-950 font-bold text-sm">🔑</span>
                    <h3 class="text-lg font-serif font-bold text-amber-400">Admin Password Reset</h3>
                </div>
                <button type="button" onclick="closeAdminResetModal()" class="text-slate-400 hover:text-white font-bold text-xl">×</button>
            </div>

            <div id="resetFeedback" class="hidden p-3 rounded-xl text-xs font-medium"></div>

            <!-- STEP 1: Enter Username/Email -->
            <div id="resetStep1" class="space-y-4">
                <p class="text-xs text-slate-400 leading-relaxed">Enter your registered admin username or email. We will send a 6-digit security OTP to your email address.</p>
                <div class="space-y-1">
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-300">Admin Username or Email</label>
                    <input type="text" id="reset_identifier" placeholder="e.g. main_admin or admin@ohmygudness.in" class="w-full px-4 py-2.5 rounded-xl bg-slate-950 border border-slate-800 focus:border-amber-500 text-sm text-slate-100 outline-none">
                </div>
                <button type="button" onclick="requestAdminOtp()" id="btnRequestOtp" class="w-full py-3 px-4 rounded-xl text-slate-950 font-bold gold-gradient shadow-md hover:scale-[1.01] transition-all text-xs uppercase tracking-wider">
                    Send Reset OTP
                </button>
            </div>

            <!-- STEP 2: Enter OTP & New Password -->
            <div id="resetStep2" class="space-y-4 hidden">
                <div class="space-y-1">
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-300">6-Digit Verification OTP</label>
                    <input type="text" id="reset_otp" maxLength="6" placeholder="123456" class="w-full px-4 py-2.5 rounded-xl bg-slate-950 border border-slate-800 focus:border-amber-500 text-sm text-slate-100 font-mono text-center tracking-widest outline-none">
                </div>
                <div class="space-y-1">
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-300">New Password (Min 6 Chars)</label>
                    <input type="password" id="reset_new_password" placeholder="••••••••" class="w-full px-4 py-2.5 rounded-xl bg-slate-950 border border-slate-800 focus:border-amber-500 text-sm text-slate-100 outline-none">
                </div>
                <div class="flex items-center justify-between text-[11px] text-slate-400">
                    <span>Didn't receive code?</span>
                    <button type="button" onclick="requestAdminOtp()" id="btnResendAdminOtp" class="text-amber-400 hover:underline font-bold">Resend OTP</button>
                </div>
                <button type="button" onclick="submitAdminPasswordReset()" id="btnSaveNewPass" class="w-full py-3 px-4 rounded-xl text-slate-950 font-bold gold-gradient shadow-md hover:scale-[1.01] transition-all text-xs uppercase tracking-wider">
                    Save New Password
                </button>
            </div>
        </div>
    </div>

    <script>
    function openAdminResetModal() {
        document.getElementById('adminResetModal').classList.remove('hidden');
        document.getElementById('adminResetModal').classList.add('flex');
    }
    function closeAdminResetModal() {
        document.getElementById('adminResetModal').classList.add('hidden');
        document.getElementById('adminResetModal').classList.remove('flex');
    }

    function showResetFeedback(msg, isError = false) {
        const box = document.getElementById('resetFeedback');
        box.className = `p-3 rounded-xl text-xs font-medium ${isError ? 'bg-rose-500/10 border border-rose-500/30 text-rose-300' : 'bg-emerald-500/10 border border-emerald-500/30 text-emerald-300'}`;
        box.textContent = msg;
        box.classList.remove('hidden');
    }

    async function requestAdminOtp() {
        const id = document.getElementById('reset_identifier').value.trim();
        if (!id) {
            showResetFeedback('Please enter your username or email address.', true);
            return;
        }
        const btn = document.getElementById('btnRequestOtp');
        btn.disabled = true;
        btn.textContent = 'Sending OTP...';

        try {
            const res = await fetch('admin.php?action=admin_request_otp', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ identifier: id })
            });
            const data = await res.json();
            if (res.ok && data.success) {
                showResetFeedback(data.message);
                document.getElementById('resetStep1').classList.add('hidden');
                document.getElementById('resetStep2').classList.remove('hidden');
            } else {
                showResetFeedback(data.message || 'Failed to send reset code.', true);
            }
        } catch (e) {
            showResetFeedback('Error connecting to backend server.', true);
        } finally {
            btn.disabled = false;
            btn.textContent = 'Send Reset OTP';
        }
    }

    async function submitAdminPasswordReset() {
        const id = document.getElementById('reset_identifier').value.trim();
        const otp = document.getElementById('reset_otp').value.trim();
        const pass = document.getElementById('reset_new_password').value.trim();

        if (otp.length !== 6) {
            showResetFeedback('Please enter the 6-digit OTP code.', true);
            return;
        }
        if (pass.length < 6) {
            showResetFeedback('New password must be at least 6 characters.', true);
            return;
        }

        const btn = document.getElementById('btnSaveNewPass');
        btn.disabled = true;
        btn.textContent = 'Saving Password...';

        try {
            const res = await fetch('admin.php?action=admin_reset_pass', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ identifier: id, otp: otp, new_password: pass })
            });
            const data = await res.json();
            if (res.ok && data.success) {
                showResetFeedback(data.message);
                setTimeout(() => {
                    closeAdminResetModal();
                    location.reload();
                }, 2000);
            } else {
                showResetFeedback(data.message || 'Failed to reset password.', true);
            }
        } catch (e) {
            showResetFeedback('Error submitting request.', true);
        } finally {
            btn.disabled = false;
            btn.textContent = 'Save New Password';
        }
    }
    </script>
</body>
</html>
<?php
exit();
endif;

// ── 7. RENDER DASHBOARD TABS FOR AUTHENTICATED ADMINS ──────────────────────
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
