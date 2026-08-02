<?php
// FEATURE 2 & 7: ADMIN PANEL DATA REFLECTION & ORDER MANAGEMENT
require_once 'config.php';

// --- ADMIN AUTHENTICATION & SETUP ---
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$database = new Database();
$db = $database->getConnection();

// Check for latest order, inquiry, or customisation query (used by real-time refresh checking on admin pages)

// Check for latest order, inquiry, or customisation query (used by real-time refresh checking on admin pages)
if (isset($_GET['action']) && $_GET['action'] === 'get_latest_order') {
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(["error" => "Unauthorized"]);
        exit();
    }
    try {
        $oQuery = "SELECT id FROM orders ORDER BY created_at DESC LIMIT 1";
        $oStmt = $db->prepare($oQuery);
        $oStmt->execute();
        $oLatest = $oStmt->fetch(PDO::FETCH_ASSOC);

        $oCountQuery = "SELECT COUNT(*) as total FROM orders";
        $oCountStmt = $db->prepare($oCountQuery);
        $oCountStmt->execute();
        $oCountRow = $oCountStmt->fetch(PDO::FETCH_ASSOC);

        $iQuery = "SELECT id FROM inquiries ORDER BY created_at DESC LIMIT 1";
        $iStmt = $db->prepare($iQuery);
        $iStmt->execute();
        $iLatest = $iStmt->fetch(PDO::FETCH_ASSOC);

        $iCountQuery = "SELECT COUNT(*) as total FROM inquiries";
        $iCountStmt = $db->prepare($iCountQuery);
        $iCountStmt->execute();
        $iCountRow = $iCountStmt->fetch(PDO::FETCH_ASSOC);

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

$message = "";
$error = "";

// Fetch All Orders
$query = "SELECT * FROM orders ORDER BY created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate Quick Stats
$totalOrders = count($orders);
$totalRevenue = 0;
$pendingCount = 0;
$inProgressCount = 0; // In-between statuses: Order Accepted, Processing, Shipped, Out for Delivery
$deliveredCount = 0;
$cancelledCount = 0;

foreach ($orders as $o) {
    $totalRevenue += (float)$o['total_amount'];
    $st = strtolower(trim($o['status']));
    if ($st === 'pending') {
        $pendingCount++;
    } elseif ($st === 'delivered') {
        $deliveredCount++;
    } elseif ($st === 'cancelled') {
        $cancelledCount++;
    } else {
        $inProgressCount++;
    }
}

function getOrderItems($db, $orderId) {
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

$pageTitle = "Order Management";
require_once 'admin_header.php';
?>

<!-- Page Header & Stats Bar -->
<div class="space-y-8">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-200 pb-6">
        <div>
            <h1 class="text-2xl sm:text-3xl font-serif font-bold text-slate-900">Order Management</h1>
            <p class="text-slate-500 text-sm mt-1">Track customer orders, manage statuses, and view live purchases.</p>
        </div>
    </div>

    <!-- Quick Stats Grid -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Total Orders</span>
            <p class="text-2xl font-bold text-slate-900 mt-1"><?php echo number_format($totalOrders); ?></p>
        </div>
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Total Revenue</span>
            <p class="text-2xl font-bold text-amber-600 mt-1">₹<?php echo number_format($totalRevenue, 2); ?></p>
        </div>
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Pending Orders</span>
            <p class="text-2xl font-bold text-amber-500 mt-1"><?php echo number_format($pendingCount); ?></p>
        </div>
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Processing / In-Progress</span>
            <p class="text-2xl font-bold text-sky-600 mt-1"><?php echo number_format($inProgressCount); ?></p>
        </div>
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Delivered</span>
            <p class="text-2xl font-bold text-emerald-600 mt-1"><?php echo number_format($deliveredCount); ?></p>
        </div>
    </div>

    <!-- Main Orders Table Card -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
        <div class="p-5 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
            <h3 class="font-semibold text-slate-800 text-base">Recent Orders</h3>
            <span class="text-xs text-slate-500">Showing all <?php echo count($orders); ?> orders</span>
        </div>

        <div class="table-wrapper">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-100/70 text-slate-600 uppercase text-[11px] font-bold tracking-wider border-b border-slate-200">
                    <tr>
                        <th class="py-3.5 px-4">Order ID & Date</th>
                        <th class="py-3.5 px-4">Customer Details</th>
                        <th class="py-3.5 px-4">Items</th>
                        <th class="py-3.5 px-4">Total</th>
                        <th class="py-3.5 px-4">Delivery Slot</th>
                        <th class="py-3.5 px-4">Delivery Address</th>
                        <th class="py-3.5 px-4">Status & Update</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($orders)): ?>
                        <tr>
                            <td colspan="7" class="py-12 text-center text-slate-400 italic">No orders found.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($orders as $order): ?>
                        <?php $items = getOrderItems($db, $order['id']); ?>
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="py-4 px-4 align-top">
                                <span class="font-mono font-bold text-slate-900 text-xs bg-slate-100 px-2 py-0.5 rounded border border-slate-200">
                                    #<?php echo htmlspecialchars(substr($order['id'], 0, 8)); ?>
                                </span>
                                <div class="text-xs text-slate-400 mt-1.5"><?php echo htmlspecialchars($order['created_at']); ?></div>
                            </td>
                            <td class="py-4 px-4 align-top">
                                <strong class="text-slate-900 font-semibold block"><?php echo htmlspecialchars($order['customer_name']); ?></strong>
                                <span class="text-xs text-slate-500 block"><?php echo htmlspecialchars($order['customer_email']); ?></span>
                                <span class="text-xs text-slate-500 block mt-0.5"><?php echo htmlspecialchars($order['customer_phone']); ?></span>
                            </td>
                            <td class="py-4 px-4 align-top">
                                <div class="space-y-2 max-w-xs">
                                    <?php foreach ($items as $item): ?>
                                        <div class="flex items-center gap-2">
                                            <?php if ($item['image']): ?>
                                                <img src="<?php echo htmlspecialchars($item['image']); ?>" class="w-9 h-9 object-cover rounded-lg border border-slate-200 flex-shrink-0" alt="Product">
                                            <?php endif; ?>
                                            <div class="min-w-0">
                                                <span class="text-xs font-semibold text-slate-800 truncate block"><?php echo htmlspecialchars($item['name']); ?></span>
                                                <span class="text-[11px] text-slate-500">Qty: <?php echo $item['quantity']; ?> × ₹<?php echo number_format($item['unit_price']); ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                            <td class="py-4 px-4 align-top">
                                <span class="font-bold text-slate-900">₹<?php echo number_format($order['total_amount'], 2); ?></span>
                            </td>
                            <td class="py-4 px-4 align-top text-xs">
                                <span class="font-semibold text-slate-700 block"><?php echo htmlspecialchars($order['delivery_date']); ?></span>
                                <span class="text-slate-500 block mt-0.5"><?php echo htmlspecialchars($order['delivery_time']); ?></span>
                            </td>
                            <td class="py-4 px-4 align-top text-xs text-slate-600 max-w-xs">
                                <?php echo htmlspecialchars($order['delivery_address']); ?>
                            </td>
                            <td class="py-4 px-4 align-top">
                                <div class="flex items-center gap-2">
                                    <select onchange="updateStatus('<?php echo $order['id']; ?>', this.value)"
                                            class="text-xs font-semibold rounded-lg border-slate-300 py-1.5 px-2 bg-white focus:ring-2 focus:ring-amber-500 focus:border-amber-500 shadow-2xs"
                                            <?php echo in_array(strtolower($order['status']), ['cancelled', 'delivered']) ? 'disabled' : ''; ?>>
                                        <option value="pending" <?php echo $order['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="order accepted" <?php echo $order['status'] == 'order accepted' ? 'selected' : ''; ?>>Order Accepted</option>
                                        <option value="processing" <?php echo $order['status'] == 'processing' ? 'selected' : ''; ?>>Processing</option>
                                        <option value="shipped" <?php echo $order['status'] == 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                        <option value="out for delivery" <?php echo $order['status'] == 'out for delivery' ? 'selected' : ''; ?>>Out for Delivery</option>
                                        <option value="delivered" <?php echo $order['status'] == 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                        <option value="cancelled" <?php echo $order['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    </select>
                                    <span id="msg-<?php echo $order['id']; ?>" class="text-[11px] font-bold text-emerald-600 opacity-0 transition-opacity">Saved!</span>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
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
            if (msgSpan) {
                msgSpan.style.opacity = 1;
                setTimeout(() => { msgSpan.style.opacity = 0; }, 2000);
            }
        } else {
            alert("Error updating status: " + (result.message || "Unknown error"));
        }
    } catch (error) {
        console.error("Request failed", error);
        alert("Failed to connect to backend");
    }
}

// Check for new orders periodically without page reload
(function() {
    let initialData = null;
    let isInitialized = false;

    function playNotificationSound() {
        try {
            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (!AudioCtx) return;
            const audioCtx = new AudioCtx();
            
            function playNote(freq, startTime, duration) {
                const osc = audioCtx.createOscillator();
                const gain = audioCtx.createGain();
                osc.connect(gain);
                gain.connect(audioCtx.destination);
                osc.type = 'sine';
                osc.frequency.setValueAtTime(freq, startTime);
                gain.gain.setValueAtTime(0.001, startTime);
                gain.gain.linearRampToValueAtTime(0.2, startTime + 0.05);
                gain.gain.exponentialRampToValueAtTime(0.001, startTime + duration);
                osc.start(startTime);
                osc.stop(startTime + duration);
            }
            playNote(1318.51, audioCtx.currentTime, 0.8);
            playNote(1760.00, audioCtx.currentTime + 0.12, 1.2);
        } catch (e) {
            console.warn(e);
        }
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
                if (data.orders.latest_id !== initialData.orders.latest_id || data.orders.total_count !== initialData.orders.total_count) {
                    playNotificationSound();
                    setTimeout(() => location.reload(), 1500);
                }
            }
        } catch (e) {
            console.error("Error checking updates:", e);
        }
    }

    setInterval(checkUpdates, 15000);
    checkUpdates();
})();
</script>

<?php require_once 'admin_footer.php'; ?>
