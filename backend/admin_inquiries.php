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

// Ensure inquiries table has columns package_name, upgrades, pincode
try {
    $cols = $db->query("SHOW COLUMNS FROM inquiries")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('package_name', $cols)) {
        $db->exec("ALTER TABLE inquiries ADD COLUMN `package_name` VARCHAR(255) DEFAULT NULL AFTER `event_type`");
    }
    if (!in_array('upgrades', $cols)) {
        $db->exec("ALTER TABLE inquiries ADD COLUMN `upgrades` TEXT DEFAULT NULL AFTER `package_name`");
    }
    if (!in_array('pincode', $cols)) {
        $db->exec("ALTER TABLE inquiries ADD COLUMN `pincode` VARCHAR(20) DEFAULT NULL AFTER `city`");
    }
} catch (Exception $e) {
    // Ignore error
}

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
            <p class="text-slate-500 text-sm mt-1">Review custom surprise packages, selected additional requirements, and client briefs.</p>
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
                        <th class="py-3.5 px-4">Client Contact</th>
                        <th class="py-3.5 px-4">Selected Package</th>
                        <th class="py-3.5 px-4">Additional Requirements</th>
                        <th class="py-3.5 px-4">Customer Message</th>
                        <th class="py-3.5 px-4">Location</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($inquiries)): ?>
                        <tr>
                            <td colspan="6" class="py-12 text-center text-slate-400 italic">No inquiries received yet.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($inquiries as $inquiry): ?>
                        <?php
                            $pkgName = !empty($inquiry['package_name']) ? $inquiry['package_name'] : $inquiry['event_type'];
                            $upgStr = trim(!empty($inquiry['upgrades']) ? $inquiry['upgrades'] : ($inquiry['service_name'] ?? ''));
                        ?>
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="py-4 px-4 align-top text-xs text-slate-500 whitespace-nowrap">
                                <div class="font-medium text-slate-700"><?php echo date('d M Y', strtotime($inquiry['created_at'])); ?></div>
                                <div class="text-[11px] text-slate-400"><?php echo date('h:i A', strtotime($inquiry['created_at'])); ?></div>
                            </td>
                            <td class="py-4 px-4 align-top">
                                <strong class="text-slate-900 font-semibold block"><?php echo htmlspecialchars($inquiry['name']); ?></strong>
                                <a href="mailto:<?php echo htmlspecialchars($inquiry['email']); ?>" class="text-xs text-slate-500 hover:text-amber-600 block"><?php echo htmlspecialchars($inquiry['email']); ?></a>
                                <a href="tel:<?php echo htmlspecialchars($inquiry['contact_no']); ?>" class="text-xs text-amber-600 hover:underline font-mono font-medium block mt-0.5">📞 <?php echo htmlspecialchars($inquiry['contact_no']); ?></a>
                            </td>
                            <td class="py-4 px-4 align-top text-xs">
                                <span class="px-2.5 py-1.5 rounded-xl bg-amber-50 text-amber-900 font-bold border border-amber-300 block shadow-2xs font-serif">
                                    👑 <?php echo htmlspecialchars($pkgName); ?>
                                </span>
                            </td>
                            <td class="py-4 px-4 align-top text-xs">
                                <?php 
                                if (!empty($upgStr) && strtolower($upgStr) !== 'none selected' && strtolower($upgStr) !== 'n/a' && strtolower($upgStr) !== 'standard inclusions' && strtolower($upgStr) !== 'standard inclusions (no add-ons)'):
                                    $upgList = array_map('trim', explode(',', $upgStr));
                                ?>
                                    <div class="flex flex-wrap gap-1.5 max-w-xs">
                                        <?php foreach ($upgList as $u): ?>
                                            <span class="px-2 py-0.5 rounded-lg bg-emerald-50 text-emerald-800 text-[11px] font-semibold border border-emerald-200 inline-flex items-center gap-1 shadow-2xs">
                                                ✨ <?php echo htmlspecialchars($u); ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-slate-400 italic text-[11px] bg-slate-50 px-2 py-1 rounded border border-slate-200/60 inline-block">
                                        Standard Inclusions Only
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="py-4 px-4 align-top text-xs text-slate-700 max-w-xs whitespace-pre-wrap leading-relaxed">
                                <?php echo htmlspecialchars($inquiry['message']); ?>
                            </td>
                            <td class="py-4 px-4 align-top text-xs text-slate-600">
                                <div class="font-medium text-slate-800"><?php echo htmlspecialchars($inquiry['address'] ?? 'N/A'); ?></div>
                                <?php if (!empty($inquiry['pincode'])): ?>
                                    <span class="inline-block mt-1 font-mono text-[11px] text-slate-600 bg-slate-100 px-2 py-0.5 rounded border border-slate-200">📍 PIN: <?php echo htmlspecialchars($inquiry['pincode']); ?></span>
                                <?php endif; ?>
                                <div class="text-[11px] text-slate-400 mt-0.5"><?php echo htmlspecialchars($inquiry['city'] ?? 'Bengaluru'); ?></div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once 'admin_footer.php'; ?>
