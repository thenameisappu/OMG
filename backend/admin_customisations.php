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

// Fetch All Customisations
$query = "SELECT * FROM customisations ORDER BY created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Customisation Requests";
require_once 'admin_header.php';
?>

<div class="space-y-8">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-200 pb-6">
        <div>
            <h1 class="text-2xl sm:text-3xl font-serif font-bold text-slate-900">Custom Floral Requests</h1>
            <p class="text-slate-500 text-sm mt-1">Manage custom arrangement specs and tailored order requests.</p>
        </div>
        <div class="flex items-center gap-3">
            <span
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span> Auto 60s Sync
            </span>
        </div>
    </div>

    <!-- Main Card -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
        <div class="p-5 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
            <h3 class="font-semibold text-slate-800 text-base">Custom Requests Log</h3>
            <span class="text-xs text-slate-500">Total: <?php echo count($items); ?></span>
        </div>

        <div class="table-wrapper">
            <table class="w-full text-left text-sm">
                <thead
                    class="bg-slate-100/70 text-slate-600 uppercase text-[11px] font-bold tracking-wider border-b border-slate-200">
                    <tr>
                        <th class="py-3.5 px-4">Date</th>
                        <th class="py-3.5 px-4">Customer Details</th>
                        <th class="py-3.5 px-4">Event Type</th>
                        <th class="py-3.5 px-4">Requirements & Specs</th>
                        <th class="py-3.5 px-4">Delivery Address</th>
                        <th class="py-3.5 px-4">City</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($items)): ?>
                        <tr>
                            <td colspan="6" class="py-12 text-center text-slate-400 italic">No customisation requests
                                received yet.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($items as $item): ?>
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="py-4 px-4 align-top text-xs text-slate-500 whitespace-nowrap">
                                <?php echo htmlspecialchars($item['created_at']); ?>
                            </td>
                            <td class="py-4 px-4 align-top">
                                <strong
                                    class="text-slate-900 font-semibold block"><?php echo htmlspecialchars($item['name']); ?></strong>
                                <span
                                    class="text-xs text-slate-500 block"><?php echo htmlspecialchars($item['email']); ?></span>
                                <span
                                    class="text-xs text-slate-500 block mt-0.5"><?php echo htmlspecialchars($item['contact_no']); ?></span>
                            </td>
                            <td class="py-4 px-4 align-top text-xs">
                                <span
                                    class="px-2.5 py-0.5 rounded bg-purple-50 text-purple-700 font-semibold border border-purple-200/60 inline-block">
                                    <?php echo htmlspecialchars($item['event_type']); ?>
                                </span>
                            </td>
                            <td
                                class="py-4 px-4 align-top text-xs text-slate-700 max-w-sm whitespace-pre-wrap leading-relaxed">
                                <?php echo htmlspecialchars($item['message']); ?>
                            </td>
                            <td class="py-4 px-4 align-top text-xs text-slate-600">
                                <?php echo htmlspecialchars($item['address'] ?? 'N/A'); ?>
                            </td>
                            <td class="py-4 px-4 align-top text-xs text-slate-600">
                                <?php echo htmlspecialchars($item['city'] ?? 'N/A'); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once 'admin_footer.php'; ?>