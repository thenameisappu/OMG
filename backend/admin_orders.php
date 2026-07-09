<?php
// FEATURE 2 & 7: ADMIN PANEL DATA REFLECTION
require_once 'config.php';

// --- ADMIN AUTHENTICATION & SETUP ---
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$database = new Database();
$db = $database->getConnection();

// Check for latest order, inquiry, or customisation query (used by real-time refresh checking on admin pages)
if (isset($_GET['action']) && $_GET['action'] === 'get_latest_order') {
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(["error" => "Unauthorized"]);
        exit();
    }
    try {
        // Orders
        $oQuery = "SELECT id FROM orders ORDER BY created_at DESC LIMIT 1";
        $oStmt = $db->prepare($oQuery);
        $oStmt->execute();
        $oLatest = $oStmt->fetch(PDO::FETCH_ASSOC);

        $oCountQuery = "SELECT COUNT(*) as total FROM orders";
        $oCountStmt = $db->prepare($oCountQuery);
        $oCountStmt->execute();
        $oCountRow = $oCountStmt->fetch(PDO::FETCH_ASSOC);

        // Inquiries
        $iQuery = "SELECT id FROM inquiries ORDER BY created_at DESC LIMIT 1";
        $iStmt = $db->prepare($iQuery);
        $iStmt->execute();
        $iLatest = $iStmt->fetch(PDO::FETCH_ASSOC);

        $iCountQuery = "SELECT COUNT(*) as total FROM inquiries";
        $iCountStmt = $db->prepare($iCountQuery);
        $iCountStmt->execute();
        $iCountRow = $iCountStmt->fetch(PDO::FETCH_ASSOC);

        // Customisations
        $cQuery = "SELECT id FROM customisations ORDER BY created_at DESC LIMIT 1";
        $cStmt = $db->prepare($cQuery);
        $cStmt->execute();
        $cLatest = $cStmt->fetch(PDO::FETCH_ASSOC);

        $cCountQuery = "SELECT COUNT(*) as total FROM customisations";
        $cCountStmt = $db->prepare($cCountQuery);
        $cCountStmt->execute();
        $cCountRow = $cCountStmt->fetch(PDO::FETCH_ASSOC);

        header('Content-Type: application/json');
        echo json_encode([
            "orders" => [
                "latest_id" => $oLatest ? $oLatest['id'] : null,
                "total_count" => $oCountRow ? (int)$oCountRow['total'] : 0
            ],
            "inquiries" => [
                "latest_id" => $iLatest ? (int)$iLatest['id'] : null,
                "total_count" => $iCountRow ? (int)$iCountRow['total'] : 0
            ],
            "customisations" => [
                "latest_id" => $cLatest ? (int)$cLatest['id'] : null,
                "total_count" => $cCountRow ? (int)$cCountRow['total'] : 0
            ]
        ]);
        exit();
    } catch (Exception $e) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(["error" => $e->getMessage()]);
        exit();
    }
}

$message = "";
$error = "";

// 1. Handle Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: admin_orders.php");
    exit();
}

// 2. Handle Login
if (isset($_POST['action']) && $_POST['action'] === 'login') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $query = "SELECT * FROM admin_users WHERE username = :username";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':username', $username);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        if (password_verify($password, $admin['password'])) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['is_main_admin'] = (bool)($admin['is_main_admin'] ?? false);
            header("Location: admin_orders.php");
            exit();
        }
        else {
            $error = "Invalid Password";
        }
    }
    else {
        $error = "Invalid Username";
    }
}

// Show Login Form if NOT logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <style>
        body { display: flex; justify-content: center; alignItems: center; height: 100vh; font-family: sans-serif; background: #f4f4f4; }
        .login-box { background: white; padding: 40px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); text-align: center; width: 300px; }
        input { padding: 10px; margin: 10px 0; width: 100%; box-sizing: border-box; border: 1px solid #ddd; border-radius: 4px; }
        button { padding: 10px 20px; background: #333; color: white; border: none; cursor: pointer; border-radius: 4px; width: 100%; }
        button:hover { background: #555; }
        .error { color: red; margin-bottom: 15px; font-size: 0.9em; }
    </style>
</head>
<body>
    <div class="login-box">
        <h2>Admin Access</h2>
        <?php if ($error)
        echo "<p class='error'>$error</p>"; ?>
        <form method="POST">
            <input type="hidden" name="action" value="login">
            <input type="text" name="username" placeholder="Username" required autofocus>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Login</button>
        </form>
    </div>
</body>
</html>
<?php
    exit(); // STOP HERE
}
// -----------------------------

// Connection established at top

// Fetch All Orders (FEATURE 7: REAL DATA)
$query = "SELECT * FROM orders ORDER BY created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

function getOrderItems($db, $orderId)
{
    // FEATURE 2: SHOW PRODUCT NAME IN ORDER_ITEMS (Using JOIN)
    $query = "SELECT 
                oi.id, 
                p.name, 
                p.image, 
                oi.quantity, 
                oi.unit_price 
              FROM order_items oi
              JOIN products p ON oi.product_id = p.id
              WHERE oi.order_id = :order_id";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':order_id', $orderId);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="120">
    <title>Admin - Order Management</title>
    <style>
        body { font-family: sans-serif; padding: 20px; background: #f4f4f4; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; border-bottom: 1px solid #ddd; text-align: left; vertical-align: top; }
        th { background: #eee; }
        .item-row { display: flex; align-items: center; gap: 10px; margin-bottom: 5px; }
        .item-img { width: 40px; height: 40px; object-fit: cover; border-radius: 4px; }
        select { padding: 5px; border-radius: 4px; border: 1px solid #ccc; }
        .status-updated { color: green; font-size: 0.9em; margin-left: 10px; opacity: 0; transition: opacity 0.5s; }
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
        <h1>Admin Order Management</h1>
        <div>
            <span style="margin-right:15px;">Welcome, <strong><?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin'); ?></strong></span>
            <a href="?logout=true" class="logout-btn">Logout</a>
        </div>
    </div>

    <div class="nav-tabs">
        <a href="admin_orders.php" class="active">Orders</a>
        <a href="admin_inquiries.php">Inquiries</a>
        <a href="admin_customisations.php">Customisations</a>
        <a href="admin_products.php">Products</a>
        <?php if (($_SESSION['admin_username'] ?? '') === 'main_admin'): ?>
            <a href="admin_manage.php">Manage Admins</a>
        <?php endif; ?>
    </div>

    <!-- Message Display -->
    <?php if ($message): ?>
        <div style="padding:10px; background:#d4edda; color:#155724; border-radius:4px; margin-bottom:15px;"><?php echo $message; ?></div>
    <?php
endif; ?>
    <?php if ($error): ?>
        <div style="padding:10px; background:#f8d7da; color:#721c24; border-radius:4px; margin-bottom:15px;"><?php echo $error; ?></div>
    <?php
endif; ?>
    
    <table>
        <thead>
            <tr>
                <th>Order ID</th>
                <th>Customer</th>
                <th>Items</th>
                <th>Total</th>
                <th>Delivery</th>
                <th>City</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($orders as $order): ?>
                <?php $items = getOrderItems($db, $order['id']); ?>
                <tr>
                    <td>
                        <small style="color:#666; font-family:monospace;"><?php echo htmlspecialchars($order['id']); ?></small><br>
                        <small><?php echo htmlspecialchars($order['created_at']); ?></small>
                    </td>
                    <td>
                        <strong><?php echo htmlspecialchars($order['customer_name']); ?></strong><br>
                        <?php echo htmlspecialchars($order['customer_email']); ?><br>
                        <?php echo htmlspecialchars($order['customer_phone']); ?>
                    </td>
                    <td>
                        <?php foreach ($items as $item): ?>
                            <div class="item-row">
                                <?php if ($item['image']): ?>
                                    <img src="<?php echo htmlspecialchars($item['image']); ?>" class="item-img" alt="Prod">
                                <?php
        endif; ?>
                                <div>
                                    <!-- FEATURE 2: Product Name instead of ID -->
                                    <strong><?php echo htmlspecialchars($item['name']); ?></strong>
                                    <br>
                                    <small>Qty: <?php echo $item['quantity']; ?> x $<?php echo $item['unit_price']; ?></small>
                                </div>
                            </div>
                        <?php
    endforeach; ?>
                    </td>
                    <td>$<?php echo htmlspecialchars($order['total_amount']); ?></td>
                    <td>
                        <?php echo htmlspecialchars($order['delivery_date']); ?><br>
                        <small><?php echo htmlspecialchars($order['delivery_time']); ?></small>
                    </td>
                    <td>
                        <!-- FEATURE 7: City = Bangalore -->
                        <?php
    // Extract city from address if possible, or show address
    echo htmlspecialchars($order['delivery_address']);
?>
                    </td>
                    <td>
                        <!-- FEATURE 1: ADMIN ORDER STATUS DROPDOWN -->
                        <div style="display:flex; align-items:center;">
                            <select onchange="updateStatus('<?php echo $order['id']; ?>', this.value)" 
                                    style="padding: 5px; border-radius: 4px; border: 1px solid #ccc;"
                                    <?php echo in_array(strtolower($order['status']), ['cancelled', 'delivered']) ? 'disabled' : ''; ?>>
                                <option value="pending" <?php echo $order['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="order accepted" <?php echo $order['status'] == 'order accepted' ? 'selected' : ''; ?>>Order Accepted</option>
                                <option value="processing" <?php echo $order['status'] == 'processing' ? 'selected' : ''; ?>>Processing</option>
                                <option value="shipped" <?php echo $order['status'] == 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                <option value="out for delivery" <?php echo $order['status'] == 'out for delivery' ? 'selected' : ''; ?>>Out for Delivery</option>
                                <option value="delivered" <?php echo $order['status'] == 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                <option value="cancelled" <?php echo $order['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                            <span id="msg-<?php echo $order['id']; ?>" class="status-updated">Updated!</span>
                        </div>
                    </td>
                </tr>
            <?php
endforeach; ?>
        </tbody>
    </table> 
</div>

<script>
async function updateStatus(orderId, newStatus) {
    const msgSpan = document.getElementById('msg-' + orderId);
    try {
        const response = await fetch('orders.php?action=update_status', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ order_id: orderId, status: newStatus })
        });
        
        const result = await response.json();
        
        if (response.ok) {
            msgSpan.style.opacity = 1;
            setTimeout(() => { msgSpan.style.opacity = 0; }, 2000);
        } else {
            alert("Error updating status: " + (result.message || "Unknown error"));
        }
    } catch (error) {
        console.error("Request failed", error);
        alert("Failed to connect to backend");
    }
}
</script>

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
