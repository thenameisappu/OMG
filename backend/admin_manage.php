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
            }
            else {
                $error = "Failed to create admin.";
            }
        }
        else {
            $error = "Username '$new_user' already exists.";
        }
    }
    else {
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
        $error = "Cannot delete the main_admin account.";
    }
    else {
        $del = $db->prepare("DELETE FROM admin_users WHERE id = :id");
        $del->bindParam(':id', $admin_id);
        if ($del->execute()) {
            $message = "Admin user deleted successfully.";
        }
        else {
            $error = "Failed to delete admin user.";
        }
    }
}

// Fetch All Admins
$admin_users_stmt = $db->query("SELECT id, username, created_at FROM admin_users ORDER BY username ASC");
$admin_users = $admin_users_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="120">
    <title>Admin - Manage Admins</title>
    <style>
        body { font-family: sans-serif; padding: 20px; background: #f4f4f4; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .logout-btn { color: red; text-decoration: none; font-weight: bold; }
        .nav-tabs { display: flex; gap: 20px; border-bottom: 2px solid #eee; margin-bottom: 20px; }
        .nav-tabs a { padding: 10px 15px; text-decoration: none; color: #555; font-weight: bold; border-bottom: 2px solid transparent; }
        .nav-tabs a.active { color: #000; border-bottom: 2px solid #000; }
        .nav-tabs a:hover { color: #000; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 12px; border-bottom: 1px solid #ddd; text-align: left; }
        th { background: #eee; }
        .btn-create { padding: 8px 15px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .btn-delete { background: none; border: none; color: red; cursor: pointer; font-weight: bold; }
        .msg { padding: 10px; border-radius: 4px; margin-bottom: 15px; }
        .msg-success { background: #d4edda; color: #155724; }
        .msg-error { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>

<div class="container">
    <div class="header-row">
        <h1>Admin User Management</h1>
        <div>
            <span style="margin-right:15px;">Welcome, <strong>main_admin</strong></span>
            <a href="?logout=true" class="logout-btn">Logout</a>
        </div>
    </div>

    <div class="nav-tabs">
        <a href="admin_orders.php">Orders</a>
        <a href="admin_inquiries.php">Inquiries</a>
        <a href="admin_customisations.php">Customisations</a>
        <a href="admin_products.php">Products</a>
        <a href="admin_manage.php" class="active">Manage Admins</a>
    </div>

    <?php if ($message): ?>
        <div class="msg msg-success"><?php echo $message; ?></div>
    <?php
endif; ?>
    <?php if ($error): ?>
        <div class="msg msg-error"><?php echo $error; ?></div>
    <?php
endif; ?>

    <details style="margin-bottom: 20px; border:1px solid #ddd; border-radius:4px; padding:15px; background:#fff;">
        <summary style="cursor:pointer; font-weight:bold; color:#555;">+ Add New Admin User</summary>
        <form method="POST" style="margin-top:15px; display:flex; gap:10px; align-items:center;">
            <input type="hidden" name="action" value="create_admin">
            <input type="text" name="new_username" placeholder="Username" required style="padding:10px; border:1px solid #ccc; border-radius:4px;">
            <input type="password" name="new_password" placeholder="Password" required style="padding:10px; border:1px solid #ccc; border-radius:4px;">
            <button type="submit" class="btn-create">Create Admin</button>
        </form>
    </details>

    <div style="border:1px solid #ddd; border-radius:4px; padding:20px; background:#fff;">
        <h3 style="margin-top:0;">Existing Admins</h3>
        <table>
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Created At</th>
                    <th style="text-align:center;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($admin_users as $admin): ?>
                <tr>
                    <td><?php echo htmlspecialchars($admin['username']); ?></td>
                    <td><?php echo htmlspecialchars($admin['created_at']); ?></td>
                    <td style="text-align:center;">
                        <?php if ($admin['username'] !== 'main_admin'): ?>
                            <form method="POST" onsubmit="return confirm('Delete this admin user?');">
                                <input type="hidden" name="action" value="delete_admin">
                                <input type="hidden" name="admin_id" value="<?php echo $admin['id']; ?>">
                                <button type="submit" class="btn-delete">Delete</button>
                            </form>
                        <?php
    else: ?>
                            <span style="color:#999; font-style:italic;">System Main</span>
                        <?php
    endif; ?>
                    </td>
                </tr>
                <?php
endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// Auto-refresh after 2 minutes (120 seconds)
setTimeout(() => {
    location.reload();
}, 120000);

// Check for new orders, inquiries, or customizations periodically
(function() {
    let initialData = null;
    let isInitialized = false;
    let reloadTimer = null;

    // Synthesize double-chime tone using Web Audio API (E6 -> A6)
    function playNotificationSound() {
        try {
            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (!AudioCtx) return;
            const audioCtx = new AudioCtx();
            
            function playNote(frequency, startTime, duration) {
                const osc = audioCtx.createOscillator();
                const gain = audioCtx.createGain();
                
                osc.connect(gain);
                gain.connect(audioCtx.destination);
                
                osc.type = 'sine';
                osc.frequency.setValueAtTime(frequency, startTime);
                
                gain.gain.setValueAtTime(0.001, startTime);
                gain.gain.linearRampToValueAtTime(0.2, startTime + 0.05);
                gain.gain.exponentialRampToValueAtTime(0.001, startTime + duration);
                
                osc.start(startTime);
                osc.stop(startTime + duration);
            }
            
            // Premium chime: E6 followed by A6
            playNote(1318.51, audioCtx.currentTime, 0.8);
            playNote(1760.00, audioCtx.currentTime + 0.12, 1.2);
        } catch (e) {
            console.warn("Failed to play notification tone:", e);
        }
    }

    // Display a beautiful luxury toast notification
    function showNotification(message) {
        let container = document.getElementById('omg-notification-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'omg-notification-container';
            container.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 99999;
                display: flex;
                flex-direction: column;
                gap: 10px;
                font-family: sans-serif;
            `;
            document.body.appendChild(container);
        }

        const toast = document.createElement('div');
        toast.style.cssText = `
            background: #111;
            color: #d4af37;
            border: 1px solid #d4af37;
            padding: 16px 24px;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 320px;
            transform: translateX(120%);
            transition: transform 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            font-weight: bold;
            letter-spacing: 0.5px;
        `;
        
        toast.innerHTML = '<span style="font-size: 20px;">🔔</span> <span>' + message + '</span>';
        container.appendChild(toast);

        // Slide in
        setTimeout(() => {
            toast.style.transform = 'translateX(0)';
        }, 100);
    }

    async function checkUpdates() {
        try {
            const response = await fetch('admin_orders.php?action=get_latest_order');
            if (!response.ok) return;
            const data = await response.json();
            
            if (!isInitialized) {
                initialData = data;
                isInitialized = true;
            } else {
                let changed = false;
                let message = "";

                if (data.orders.latest_id !== initialData.orders.latest_id || data.orders.total_count !== initialData.orders.total_count) {
                    changed = true;
                    message = "New Order Received!";
                } else if (data.inquiries.latest_id !== initialData.inquiries.latest_id || data.inquiries.total_count !== initialData.inquiries.total_count) {
                    changed = true;
                    message = "New Inquiry Received!";
                } else if (data.customisations.latest_id !== initialData.customisations.latest_id || data.customisations.total_count !== initialData.customisations.total_count) {
                    changed = true;
                    message = "New Customisation Request!";
                }

                if (changed && !reloadTimer) {
                    playNotificationSound();
                    showNotification(message);
                    
                    // Delay reload by 2.5 seconds to let notification play and be seen
                    reloadTimer = setTimeout(() => {
                        location.reload();
                    }, 2500);
                }
            }
        } catch (e) {
            console.error("Error checking updates:", e);
        }
    }

    // Poll every 10 seconds
    setInterval(checkUpdates, 10000);
    // Initialize
    checkUpdates();
})();
</script>

</body>
</html>
