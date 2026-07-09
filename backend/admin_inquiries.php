<?php
require_once 'config.php';

// --- ADMIN AUTHENTICATION ---
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$database = new Database();
$db = $database->getConnection();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_orders.php");
    exit();
}

// 1. Handle Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: admin_orders.php");
    exit();
}

// Fetch All Inquiries
$query = "SELECT * FROM inquiries ORDER BY created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$inquiries = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="120">
    <title>Admin - Inquiries Management</title>
    <style>
        body { font-family: sans-serif; padding: 20px; background: #f4f4f4; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; border-bottom: 1px solid #ddd; text-align: left; vertical-align: top; }
        th { background: #eee; }
        .header-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .logout-btn { color: red; text-decoration: none; font-weight: bold; }
        .nav-tabs { display: flex; gap: 20px; border-bottom: 2px solid #eee; margin-bottom: 20px; }
        .nav-tabs a { padding: 10px 15px; text-decoration: none; color: #555; font-weight: bold; border-bottom: 2px solid transparent; }
        .nav-tabs a.active { color: #000; border-bottom: 2px solid #000; }
        .nav-tabs a:hover { color: #000; }
    </style>
</head>
<body>

<div class="container">
    <div class="header-row">
        <h1>Admin Inquiry Management</h1>
        <div>
            <span style="margin-right:15px;">Welcome, <strong><?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin'); ?></strong></span>
            <a href="?logout=true" class="logout-btn">Logout</a>
        </div>
    </div>

    <div class="nav-tabs">
        <a href="admin_orders.php">Orders</a>
        <a href="admin_inquiries.php" class="active">Inquiries</a>
        <a href="admin_customisations.php">Customisations</a>
        <a href="admin_products.php">Products</a>
        <?php if (($_SESSION['admin_username'] ?? '') === 'main_admin'): ?>
            <a href="admin_manage.php">Manage Admins</a>
        <?php endif; ?>
    </div>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Contact Info</th>
                <th>Event & Service</th>
                <th>Message</th>
                <th>Address</th>
                <th>City</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($inquiries)): ?>
                <tr><td colspan="5" style="text-align:center;">No inquiries found.</td></tr>
            <?php
endif; ?>
            <?php foreach ($inquiries as $inquiry): ?>
                <tr>
                    <td>
                        <?php echo htmlspecialchars($inquiry['created_at']); ?>
                    </td>
                    <td>
                        <strong><?php echo htmlspecialchars($inquiry['name']); ?></strong><br>
                        <?php echo htmlspecialchars($inquiry['email']); ?><br>
                        <?php echo htmlspecialchars($inquiry['contact_no']); ?>
                    </td>
                    <td>
                        <strong>Type:</strong> <?php echo htmlspecialchars($inquiry['event_type']); ?><br>
                        <strong>Service:</strong> <?php echo htmlspecialchars($inquiry['service_name'] ?? 'N/A'); ?>
                    </td>
                    <td>
                        <div style="max-width:300px; white-space: pre-wrap;"><?php echo htmlspecialchars($inquiry['message']); ?></div>
                    </td>
                    <td>
                        <?php echo htmlspecialchars($inquiry['address'] ?? 'N/A'); ?>
                    </td>
                    <td>
                        <?php echo htmlspecialchars($inquiry['city'] ?? 'N/A'); ?>
                    </td>
                </tr>
            <?php
endforeach; ?>
        </tbody>
    </table>
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
