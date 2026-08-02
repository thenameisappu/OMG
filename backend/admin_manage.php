<?php
require_once 'config.php';

// --- ADMIN AUTHENTICATION ---
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$database = new Database();
$db = $database->getConnection();

// Restricted to main_admin only
if (!isset($_SESSION['admin_logged_in']) || ($_SESSION['admin_username'] ?? '') !== 'main_admin') {
    header("Location: admin_orders.php");
    exit();
}

// 24-Hour Session Lifetime Check
$sessionTimeout = getenv('SESSION_TIMEOUT_SECONDS') ? (int) getenv('SESSION_TIMEOUT_SECONDS') : 86400;
if (isset($_SESSION['admin_last_activity']) && (time() - $_SESSION['admin_last_activity'] > $sessionTimeout)) {
    session_unset();
    session_destroy();
    header("Location: admin_orders.php?expired=1");
    exit();
}
$_SESSION['admin_last_activity'] = time();

$message = "";
$error = "";

// 1. Handle Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: admin_orders.php");
    exit();
}

// 2. Handle Add New Admin
if (isset($_POST['action']) && $_POST['action'] === 'create_admin') {
    $new_user = trim($_POST['new_username'] ?? '');
    $new_pass = trim($_POST['new_password'] ?? '');

    if (!empty($new_user) && !empty($new_pass)) {
        $check = $db->prepare("SELECT id FROM admin_users WHERE username = :user");
        $check->bindParam(':user', $new_user);
        $check->execute();

        if ($check->rowCount() == 0) {
            $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
            $ins = $db->prepare("INSERT INTO admin_users (username, password) VALUES (:user, :pass)");
            $ins->bindParam(':user', $new_user);
            $ins->bindParam(':pass', $hashed);
            if ($ins->execute()) {
                $message = "New admin '$new_user' created successfully!";
            } else {
                $error = "Failed to create admin.";
            }
        } else {
            $error = "Username '$new_user' already exists.";
        }
    } else {
        $error = "Username and Password are required.";
    }
}

// 3. Handle Delete Admin
if (isset($_POST['action']) && $_POST['action'] === 'delete_admin') {
    $admin_id = $_POST['admin_id'] ?? '';

    // Prevent self-deletion
    $check_self = $db->prepare("SELECT username FROM admin_users WHERE id = :id");
    $check_self->bindParam(':id', $admin_id);
    $check_self->execute();
    $admin_to_del = $check_self->fetch(PDO::FETCH_ASSOC);

    if ($admin_to_del && $admin_to_del['username'] === 'main_admin') {
        $error = "Cannot delete the root main_admin account.";
    } else {
        $del = $db->prepare("DELETE FROM admin_users WHERE id = :id");
        $del->bindParam(':id', $admin_id);
        if ($del->execute()) {
            $message = "Admin user deleted successfully.";
        } else {
            $error = "Failed to delete admin user.";
        }
    }
}

// Fetch All Admins
$admin_users_stmt = $db->query("SELECT id, username, created_at FROM admin_users ORDER BY username ASC");
$admin_users = $admin_users_stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Manage Admins";
require_once 'admin_header.php';
?>

<div class="space-y-8 max-w-5xl mx-auto">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-200 pb-6">
        <div>
            <h1 class="text-2xl sm:text-3xl font-serif font-bold text-slate-900">Administrator Accounts</h1>
            <p class="text-slate-500 text-sm mt-1">Manage system administrators and security credentials (Root Access
                Only).</p>
        </div>
        <span
            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-200">
            🛡️ Root Control Active
        </span>
    </div>

    <?php if ($message): ?>
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl text-sm font-medium">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 rounded-xl text-sm font-medium">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <!-- Create Admin Card -->
    <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm">
        <h3 class="text-base font-semibold text-slate-900 mb-4 flex items-center gap-2">Add New Administrator Account
        </h3>
        <form method="POST" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <input type="hidden" name="action" value="create_admin">
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1">New Username</label>
                <input type="text" name="new_username" required placeholder="username"
                    class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-amber-500 outline-none">
            </div>
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1">New Password</label>
                <input type="password" name="new_password" required placeholder="••••••••"
                    class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-amber-500 outline-none">
            </div>
            <div class="flex items-end">
                <button type="submit"
                    class="w-full py-2.5 px-4 gold-gradient text-slate-950 font-bold rounded-xl text-sm shadow-md hover:shadow-lg transition-all">
                    Create Admin User
                </button>
            </div>
        </form>
    </div>

    <!-- Active Admins Table -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
        <div class="p-5 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
            <h3 class="font-semibold text-slate-800 text-base">Active Administrator List</h3>
            <span class="text-xs text-slate-500">Total Admins: <?php echo count($admin_users); ?></span>
        </div>

        <div class="table-wrapper">
            <table class="w-full text-left text-sm">
                <thead
                    class="bg-slate-100/70 text-slate-600 uppercase text-[11px] font-bold tracking-wider border-b border-slate-200">
                    <tr>
                        <th class="py-3.5 px-4">Username</th>
                        <th class="py-3.5 px-4">Role</th>
                        <th class="py-3.5 px-4">Created Date</th>
                        <th class="py-3.5 px-4 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($admin_users as $admin): ?>
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="py-4 px-4 font-bold text-slate-900">
                                <?php echo htmlspecialchars($admin['username']); ?>
                            </td>
                            <td class="py-4 px-4">
                                <?php if ($admin['username'] === 'main_admin'): ?>
                                    <span
                                        class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800 border border-amber-300">Root
                                        Admin</span>
                                <?php else: ?>
                                    <span
                                        class="px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-700">Administrator</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-4 px-4 text-xs text-slate-500">
                                <?php echo htmlspecialchars($admin['created_at']); ?>
                            </td>
                            <td class="py-4 px-4 text-right">
                                <?php if ($admin['username'] !== 'main_admin'): ?>
                                    <form method="POST"
                                        onsubmit="return confirm('Are you sure you want to delete this admin account?');"
                                        class="inline">
                                        <input type="hidden" name="action" value="delete_admin">
                                        <input type="hidden" name="admin_id" value="<?php echo $admin['id']; ?>">
                                        <button type="submit"
                                            class="text-xs font-bold text-rose-600 hover:text-rose-800 bg-rose-50 hover:bg-rose-100 px-3 py-1.5 rounded-lg transition-colors border border-rose-200">
                                            Delete Account
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-xs text-slate-400 italic">Protected</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once 'admin_footer.php'; ?>