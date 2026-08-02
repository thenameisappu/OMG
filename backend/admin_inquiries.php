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

// Fetch All Inquiries

// Fetch All Inquiries
$query = "SELECT * FROM inquiries ORDER BY created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$inquiries = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Bespoke Inquiries";
require_once 'admin_header.php';
?>

<div class="space-y-8">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-200 pb-6">
        <div>
            <h1 class="text-2xl sm:text-3xl font-serif font-bold text-slate-900">Bespoke Event Inquiries</h1>
            <p class="text-slate-500 text-sm mt-1">Review custom surprise requests and event planning inquiries.</p>
        </div>
        <div class="flex items-center gap-3">
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span> Auto 60s Sync
            </span>
        </div>
    </div>

    <!-- Main Card -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
        <div class="p-5 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
            <h3 class="font-semibold text-slate-800 text-base">Inquiries Log</h3>
            <span class="text-xs text-slate-500">Total: <?php echo count($inquiries); ?></span>
        </div>

        <div class="table-wrapper">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-100/70 text-slate-600 uppercase text-[11px] font-bold tracking-wider border-b border-slate-200">
                    <tr>
                        <th class="py-3.5 px-4">Date</th>
                        <th class="py-3.5 px-4">Contact Info</th>
                        <th class="py-3.5 px-4">Event & Service</th>
                        <th class="py-3.5 px-4">Customer Message</th>
                        <th class="py-3.5 px-4">Address</th>
                        <th class="py-3.5 px-4">City</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($inquiries)): ?>
                        <tr>
                            <td colspan="6" class="py-12 text-center text-slate-400 italic">No inquiries received yet.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($inquiries as $inquiry): ?>
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="py-4 px-4 align-top text-xs text-slate-500 whitespace-nowrap">
                                <?php echo htmlspecialchars($inquiry['created_at']); ?>
                            </td>
                            <td class="py-4 px-4 align-top">
                                <strong class="text-slate-900 font-semibold block"><?php echo htmlspecialchars($inquiry['name']); ?></strong>
                                <span class="text-xs text-slate-500 block"><?php echo htmlspecialchars($inquiry['email']); ?></span>
                                <span class="text-xs text-slate-500 block mt-0.5"><?php echo htmlspecialchars($inquiry['contact_no']); ?></span>
                            </td>
                            <td class="py-4 px-4 align-top text-xs">
                                <span class="px-2 py-0.5 rounded bg-amber-50 text-amber-700 font-semibold border border-amber-200/60 inline-block mb-1">
                                    <?php echo htmlspecialchars($inquiry['event_type']); ?>
                                </span>
                                <div class="text-slate-700 font-medium"><?php echo htmlspecialchars($inquiry['service_name'] ?? 'N/A'); ?></div>
                            </td>
                            <td class="py-4 px-4 align-top text-xs text-slate-700 max-w-sm whitespace-pre-wrap leading-relaxed">
                                <?php echo htmlspecialchars($inquiry['message']); ?>
                            </td>
                            <td class="py-4 px-4 align-top text-xs text-slate-600">
                                <?php echo htmlspecialchars($inquiry['address'] ?? 'N/A'); ?>
                            </td>
                            <td class="py-4 px-4 align-top text-xs text-slate-600">
                                <?php echo htmlspecialchars($inquiry['city'] ?? 'N/A'); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once 'admin_footer.php'; ?>
