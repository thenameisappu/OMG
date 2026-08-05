<?php
require_once 'config.php';

// --- ADMIN AUTHENTICATION & SETUP ---
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();
ensureAuthTablesExist($db);

// Inline Migration Protection: ensure columns exist before running queries
try {
    @$db->exec("ALTER TABLE `admin_users` ADD COLUMN `name` VARCHAR(255) AFTER `username`");
} catch (Exception $e) {
}
try {
    @$db->exec("ALTER TABLE `admin_users` ADD COLUMN `email` VARCHAR(191) UNIQUE AFTER `password`");
} catch (Exception $e) {
}
try {
    @$db->exec("ALTER TABLE `admin_users` ADD COLUMN `is_main_admin` TINYINT(1) DEFAULT 0 AFTER `email`");
} catch (Exception $e) {
}
try {
    @$db->exec("ALTER TABLE `admin_users` ADD COLUMN `otp_code` VARCHAR(6) AFTER `is_main_admin`");
} catch (Exception $e) {
}
try {
    @$db->exec("ALTER TABLE `admin_users` ADD COLUMN `otp_expiry` DATETIME AFTER `otp_code`");
} catch (Exception $e) {
}

$message = "";
$error = "";

// Fetch currently logged-in admin details
$loggedInUsername = $_SESSION['admin_username'] ?? '';
$currentAdminStmt = $db->prepare("SELECT * FROM admin_users WHERE username = :u LIMIT 1");
$currentAdminStmt->execute([':u' => $loggedInUsername]);
$currentAdmin = $currentAdminStmt->fetch(PDO::FETCH_ASSOC);

if (!$currentAdmin) {
    header("Location: admin.php?logout=1");
    exit();
}

$isMainAdmin = (bool) ($currentAdmin['is_main_admin'] ?? ($currentAdmin['username'] === 'main_admin'));

// ── 1. HANDLE LOGGED-IN ADMIN PROFILE UPDATE ───────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'update_my_profile') {
    $myName = trim($_POST['my_name'] ?? '');
    $myEmail = strtolower(trim($_POST['my_email'] ?? ''));
    $currentPass = trim($_POST['current_password'] ?? '');
    $newPass = trim($_POST['new_password'] ?? '');
    $confirmPass = trim($_POST['confirm_password'] ?? '');

    if (empty($currentPass)) {
        $error = "Please enter your current password to confirm profile updates.";
    } elseif (!password_verify($currentPass, $currentAdmin['password'])) {
        $error = "Incorrect current password.";
    } elseif (!empty($myEmail) && !filter_var($myEmail, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        // Check if email is already taken by another admin
        if (!empty($myEmail)) {
            $emailCheck = $db->prepare("SELECT id FROM admin_users WHERE email = :email AND id != :id LIMIT 1");
            $emailCheck->execute([':email' => $myEmail, ':id' => $currentAdmin['id']]);
            if ($emailCheck->rowCount() > 0) {
                $error = "Email address '$myEmail' is already in use by another administrator.";
            }
        }

        if (empty($error)) {
            $updatePassword = false;
            $newHashed = $currentAdmin['password'];

            if (!empty($newPass)) {
                if (strlen($newPass) < 6) {
                    $error = "New password must be at least 6 characters long.";
                } elseif ($newPass !== $confirmPass) {
                    $error = "New password and confirmation password do not match.";
                } else {
                    $newHashed = password_hash($newPass, PASSWORD_DEFAULT);
                    $updatePassword = true;
                }
            }

            if (empty($error)) {
                $upStmt = $db->prepare("UPDATE admin_users SET name = :name, email = :email, password = :pass WHERE id = :id");
                if ($upStmt->execute([':name' => $myName, ':email' => $myEmail, ':pass' => $newHashed, ':id' => $currentAdmin['id']])) {
                    $message = "Your profile and security credentials have been updated successfully!";
                    // Refresh current admin data
                    $currentAdminStmt->execute([':u' => $loggedInUsername]);
                    $currentAdmin = $currentAdminStmt->fetch(PDO::FETCH_ASSOC);
                } else {
                    $error = "Failed to update profile details.";
                }
            }
        }
    }
}

// ── 2. HANDLE CREATE NEW ADMIN (ROOT MAIN ADMIN ONLY) ──────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'create_admin') {
    if (!$isMainAdmin) {
        $error = "Only the Root Main Admin can create new administrator accounts.";
    } else {
        $new_user = trim($_POST['new_username'] ?? '');
        $new_name = trim($_POST['new_name'] ?? '');
        $new_email = strtolower(trim($_POST['new_email'] ?? ''));
        $new_pass = trim($_POST['new_password'] ?? '');

        if (empty($new_user) || empty($new_email) || empty($new_pass)) {
            $error = "Admin ID (Username), Email Address, and Initial Password are required.";
        } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        } elseif (strlen($new_pass) < 6) {
            $error = "Password must be at least 6 characters long.";
        } else {
            $check = $db->prepare("SELECT id FROM admin_users WHERE username = :user OR (email = :email AND email IS NOT NULL AND email != '')");
            $check->execute([':user' => $new_user, ':email' => $new_email]);

            if ($check->rowCount() > 0) {
                $error = "An administrator account with this Username or Email already exists.";
            } else {
                $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
                $ins = $db->prepare("INSERT INTO admin_users (username, name, email, password) VALUES (:user, :name, :email, :pass)");
                if ($ins->execute([':user' => $new_user, ':name' => $new_name ?: $new_user, ':email' => $new_email, ':pass' => $hashed])) {
                    $message = "New admin account '$new_user' created successfully!";
                } else {
                    $error = "Failed to create new admin account.";
                }
            }
        }
    }
}

// ── 3. HANDLE EDIT OTHER ADMIN (ROOT MAIN ADMIN ONLY) ──────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'edit_admin') {
    if (!$isMainAdmin) {
        $error = "Only the Root Main Admin can modify other administrator accounts.";
    } else {
        $edit_id = $_POST['edit_id'] ?? '';
        $edit_name = trim($_POST['edit_name'] ?? '');
        $edit_email = strtolower(trim($_POST['edit_email'] ?? ''));
        $edit_pass = trim($_POST['edit_password'] ?? '');

        if (empty($edit_id)) {
            $error = "Invalid administrator ID.";
        } else {
            $tCheck = $db->prepare("SELECT * FROM admin_users WHERE id = :id LIMIT 1");
            $tCheck->execute([':id' => $edit_id]);
            $targetAdmin = $tCheck->fetch(PDO::FETCH_ASSOC);

            if (!$targetAdmin) {
                $error = "Target administrator not found.";
            } else {
                if (!empty($edit_email) && !filter_var($edit_email, FILTER_VALIDATE_EMAIL)) {
                    $error = "Please enter a valid email address.";
                } else {
                    // Check email uniqueness
                    if (!empty($edit_email)) {
                        $eCheck = $db->prepare("SELECT id FROM admin_users WHERE email = :email AND id != :id LIMIT 1");
                        $eCheck->execute([':email' => $edit_email, ':id' => $edit_id]);
                        if ($eCheck->rowCount() > 0) {
                            $error = "Email address '$edit_email' is already used by another admin.";
                        }
                    }

                    if (empty($error)) {
                        $newPassHash = $targetAdmin['password'];
                        if (!empty($edit_pass)) {
                            if (strlen($edit_pass) < 6) {
                                $error = "New password must be at least 6 characters.";
                            } else {
                                $newPassHash = password_hash($edit_pass, PASSWORD_DEFAULT);
                            }
                        }

                        if (empty($error)) {
                            $uStmt = $db->prepare("UPDATE admin_users SET name = :name, email = :email, password = :pass WHERE id = :id");
                            if ($uStmt->execute([':name' => $edit_name, ':email' => $edit_email, ':pass' => $newPassHash, ':id' => $edit_id])) {
                                $message = "Administrator '{$targetAdmin['username']}' updated successfully!";
                            } else {
                                $error = "Failed to update administrator account.";
                            }
                        }
                    }
                }
            }
        }
    }
}

// ── 4. HANDLE DELETE ADMIN (ROOT MAIN ADMIN ONLY) ──────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'delete_admin') {
    if (!$isMainAdmin) {
        $error = "Only the Root Main Admin can delete administrator accounts.";
    } else {
        $admin_id = $_POST['admin_id'] ?? '';

        $check_self = $db->prepare("SELECT username FROM admin_users WHERE id = :id");
        $check_self->execute([':id' => $admin_id]);
        $admin_to_del = $check_self->fetch(PDO::FETCH_ASSOC);

        if ($admin_to_del && $admin_to_del['username'] === 'main_admin') {
            $error = "Cannot delete the root main_admin account.";
        } elseif ($admin_to_del && $admin_to_del['username'] === $loggedInUsername) {
            $error = "You cannot delete your own logged-in account.";
        } else {
            $del = $db->prepare("DELETE FROM admin_users WHERE id = :id");
            if ($del->execute([':id' => $admin_id])) {
                $message = "Administrator account deleted successfully.";
            } else {
                $error = "Failed to delete admin user.";
            }
        }
    }
}

// Fetch All Admins for Table
$admin_users_stmt = $db->query("SELECT id, username, name, email, is_main_admin, created_at FROM admin_users ORDER BY username ASC");
$admin_users = $admin_users_stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Manage Admins & Profile";
require_once 'admin_header.php';
?>

<div class="space-y-8 max-w-5xl mx-auto">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-200 pb-6">
        <div>
            <h1 class="text-2xl sm:text-3xl font-serif font-bold text-slate-900">Administrator Accounts & Profile</h1>
            <p class="text-slate-500 text-sm mt-1">Manage your admin profile, update credentials, and manage team
                accounts.</p>
        </div>
        <span
            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-200">
            🛡️ Admin Security Control
        </span>
    </div>

    <?php if ($message): ?>
        <div
            class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl text-sm font-medium flex items-center gap-2">
            <span>✅</span> <span><?php echo htmlspecialchars($message); ?></span>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div
            class="bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 rounded-xl text-sm font-medium flex items-center gap-2">
            <span>❌</span> <span><?php echo htmlspecialchars($error); ?></span>
        </div>
    <?php endif; ?>

    <!-- CARD 1: ADD NEW ADMIN ACCOUNT (FOR ROOT MAIN ADMIN ONLY) -->

    <!-- CARD 2: ADD NEW ADMIN ACCOUNT (FOR ROOT MAIN ADMIN ONLY) -->
    <?php if ($isMainAdmin): ?>
        <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm space-y-4">
            <h3 class="text-base font-semibold text-slate-900 flex items-center gap-2">
                ➕ Add New Administrator Account
            </h3>
            <form method="POST" class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                <input type="hidden" name="action" value="create_admin">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1">New Admin ID
                        (Username) *</label>
                    <input type="text" name="new_username" required placeholder="e.g. appu"
                        class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-amber-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1">Display Name</label>
                    <input type="text" name="new_name" placeholder="e.g. Appu Kumar"
                        class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-amber-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1">Admin Email
                        *</label>
                    <input type="email" name="new_email" required placeholder="admin@domain.com"
                        class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-amber-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1">New Password
                        *</label>
                    <input type="password" name="new_password" required placeholder="••••••••"
                        class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-amber-500 outline-none">
                </div>
                <div class="sm:col-span-4 flex justify-end">
                    <button type="submit"
                        class="py-2.5 px-6 gold-gradient text-slate-950 font-bold rounded-xl text-sm shadow-md hover:shadow-lg transition-all">
                        Create Admin User
                    </button>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <!-- CARD 3: ACTIVE ADMINS TABLE -->
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
                        <th class="py-3.5 px-4">Admin ID (Username)</th>
                        <th class="py-3.5 px-4">Display Name</th>
                        <th class="py-3.5 px-4">Email Address</th>
                        <th class="py-3.5 px-4">Role</th>
                        <th class="py-3.5 px-4">Created Date</th>
                        <th class="py-3.5 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($admin_users as $admin): ?>
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="py-4 px-4 font-bold text-slate-900">
                                <?php echo htmlspecialchars($admin['username']); ?>
                            </td>
                            <td class="py-4 px-4 text-slate-800 font-medium">
                                <?php echo htmlspecialchars($admin['name'] ?: $admin['username']); ?>
                            </td>
                            <td class="py-4 px-4 text-xs text-slate-600">
                                <?php echo htmlspecialchars($admin['email'] ?: 'Not set'); ?>
                            </td>
                            <td class="py-4 px-4">
                                <?php if ($admin['username'] === 'main_admin' || !empty($admin['is_main_admin'])): ?>
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
                                <div class="flex items-center justify-end gap-2">
                                    <?php if ($isMainAdmin): ?>
                                        <button type="button"
                                            onclick="openEditAdminModal(<?php echo htmlspecialchars(json_encode($admin)); ?>)"
                                            class="text-xs font-bold text-amber-700 hover:text-amber-900 bg-amber-50 hover:bg-amber-100 px-3 py-1.5 rounded-lg transition-colors border border-amber-200">
                                            Edit
                                        </button>

                                        <?php if ($admin['username'] !== 'main_admin' && $admin['username'] !== $loggedInUsername): ?>
                                            <form method="POST"
                                                onsubmit="return confirm('Are you sure you want to delete this admin account?');"
                                                class="inline">
                                                <input type="hidden" name="action" value="delete_admin">
                                                <input type="hidden" name="admin_id" value="<?php echo $admin['id']; ?>">
                                                <button type="submit"
                                                    class="text-xs font-bold text-rose-600 hover:text-rose-800 bg-rose-50 hover:bg-rose-100 px-3 py-1.5 rounded-lg transition-colors border border-rose-200">
                                                    Delete
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-xs text-slate-400 italic">Protected</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-xs text-slate-400 italic">View Only</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- EDIT ADMIN MODAL (FOR ROOT MAIN ADMIN) -->
<?php if ($isMainAdmin): ?>
    <div id="editAdminModal"
        class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-md hidden items-center justify-center p-4">
        <div class="bg-white border border-slate-200 max-w-lg w-full rounded-3xl p-6 shadow-2xl space-y-6">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <h3 class="text-lg font-serif font-bold text-slate-900">Edit Administrator Account</h3>
                <button type="button" onclick="closeEditAdminModal()"
                    class="text-slate-400 hover:text-slate-600 font-bold text-xl">×</button>
            </div>

            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="edit_admin">
                <input type="hidden" name="edit_id" id="modal_edit_id">

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1">Admin ID
                        (Username)</label>
                    <input type="text" id="modal_edit_username" disabled
                        class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-100 text-slate-500 text-sm font-semibold cursor-not-allowed">
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1">Display Name</label>
                    <input type="text" name="edit_name" id="modal_edit_name" placeholder="Full Display Name"
                        class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-amber-500 outline-none">
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1">Admin Email
                        Address</label>
                    <input type="email" name="edit_email" id="modal_edit_email" placeholder="admin@domain.com"
                        class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-amber-500 outline-none">
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1">Reset Password
                        (Optional)</label>
                    <input type="password" name="edit_password" placeholder="Leave blank to keep existing password"
                        class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-sm focus:ring-2 focus:ring-amber-500 outline-none">
                    <span class="text-[11px] text-slate-400 mt-1 block">Only enter a password if you want to reset this
                        admin's credentials.</span>
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" onclick="closeEditAdminModal()"
                        class="py-2.5 px-4 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-sm transition-all">Cancel</button>
                    <button type="submit"
                        class="py-2.5 px-6 gold-gradient text-slate-950 font-bold rounded-xl text-sm shadow-md hover:shadow-lg transition-all">Save
                        Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openEditAdminModal(admin) {
            document.getElementById('modal_edit_id').value = admin.id;
            document.getElementById('modal_edit_username').value = admin.username;
            document.getElementById('modal_edit_name').value = admin.name || admin.username;
            document.getElementById('modal_edit_email').value = admin.email || '';
            document.getElementById('editAdminModal').classList.remove('hidden');
            document.getElementById('editAdminModal').classList.add('flex');
        }
        function closeEditAdminModal() {
            document.getElementById('editAdminModal').classList.add('hidden');
            document.getElementById('editAdminModal').classList.remove('flex');
        }
    </script>
<?php endif; ?>

<?php require_once 'admin_footer.php'; ?>