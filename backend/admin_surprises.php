<?php
// ADMIN PANEL: SURPRISE EXPERIENCES & PINCODES MANAGEMENT
require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$database = new Database();
$db = $database->getConnection();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin.php");
    exit();
}

// Ensure database tables exist
ensureSurpriseTablesExist($db);

$message = "";
$error = "";

// ── CRUD HANDLERS ─────────────────────────────────────────────────────────

// 1. Save / Update Experience
if (isset($_POST['action']) && $_POST['action'] === 'save_experience') {
    $id = $_POST['id'] ?? null;
    $title = trim($_POST['title'] ?? '');
    $subtitle = trim($_POST['subtitle'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $badge = trim($_POST['badge'] ?? '');
    $base_price = (float) ($_POST['base_price'] ?? 0);
    $image = trim($_POST['image'] ?? '');
    $features_raw = trim($_POST['features'] ?? '');
    $display_order = (int) ($_POST['display_order'] ?? 0);
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // Convert features to JSON array
    $featureList = array_values(array_filter(array_map('trim', explode("\n", $features_raw))));
    $features_json = json_encode($featureList);

    if ($title && $base_price > 0 && $image) {
        if ($id) {
            $stmt = $db->prepare("UPDATE `surprise_experiences` SET `title` = :title, `subtitle` = :subtitle, `description` = :description, `badge` = :badge, `base_price` = :base_price, `image` = :image, `features` = :features, `display_order` = :display_order, `is_active` = :is_active WHERE `id` = :id");
            $stmt->execute([
                ':title' => $title,
                ':subtitle' => $subtitle,
                ':description' => $description,
                ':badge' => $badge,
                ':base_price' => $base_price,
                ':image' => $image,
                ':features' => $features_json,
                ':display_order' => $display_order,
                ':is_active' => $is_active,
                ':id' => $id
            ]);
            $message = "Base Experience updated successfully!";
        } else {
            $stmt = $db->prepare("INSERT INTO `surprise_experiences` (`title`, `subtitle`, `description`, `badge`, `base_price`, `image`, `features`, `display_order`, `is_active`) VALUES (:title, :subtitle, :description, :badge, :base_price, :image, :features, :display_order, :is_active)");
            $stmt->execute([
                ':title' => $title,
                ':subtitle' => $subtitle,
                ':description' => $description,
                ':badge' => $badge,
                ':base_price' => $base_price,
                ':image' => $image,
                ':features' => $features_json,
                ':display_order' => $display_order,
                ':is_active' => $is_active
            ]);
            $message = "New Base Experience created successfully!";
        }
    } else {
        $error = "Title, Base Price, and Image URL are required.";
    }
}

// 2. Delete Experience
if (isset($_POST['action']) && $_POST['action'] === 'delete_experience') {
    $id = $_POST['id'] ?? null;
    if ($id) {
        $stmt = $db->prepare("DELETE FROM `surprise_experiences` WHERE `id` = :id");
        $stmt->execute([':id' => $id]);
        $message = "Experience deleted successfully.";
    }
}

// 3. Save / Update Upgrade
if (isset($_POST['action']) && $_POST['action'] === 'save_upgrade') {
    $id = $_POST['id'] ?? null;
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $icon = trim($_POST['icon'] ?? 'Sparkles');
    $price = (float) ($_POST['price'] ?? 0);
    $display_order = (int) ($_POST['display_order'] ?? 0);
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if ($name && $price >= 0) {
        if ($id) {
            $stmt = $db->prepare("UPDATE `surprise_upgrades` SET `name` = :name, `description` = :description, `icon` = :icon, `price` = :price, `display_order` = :display_order, `is_active` = :is_active WHERE `id` = :id");
            $stmt->execute([
                ':name' => $name,
                ':description' => $description,
                ':icon' => $icon,
                ':price' => $price,
                ':display_order' => $display_order,
                ':is_active' => $is_active,
                ':id' => $id
            ]);
            $message = "Experience Upgrade updated successfully!";
        } else {
            $stmt = $db->prepare("INSERT INTO `surprise_upgrades` (`name`, `description`, `icon`, `price`, `display_order`, `is_active`) VALUES (:name, :description, :icon, :price, :display_order, :is_active)");
            $stmt->execute([
                ':name' => $name,
                ':description' => $description,
                ':icon' => $icon,
                ':price' => $price,
                ':display_order' => $display_order,
                ':is_active' => $is_active
            ]);
            $message = "New Experience Upgrade added successfully!";
        }
    } else {
        $error = "Upgrade Name and Price are required.";
    }
}

// 4. Delete Upgrade
if (isset($_POST['action']) && $_POST['action'] === 'delete_upgrade') {
    $id = $_POST['id'] ?? null;
    if ($id) {
        $stmt = $db->prepare("DELETE FROM `surprise_upgrades` WHERE `id` = :id");
        $stmt->execute([':id' => $id]);
        $message = "Upgrade deleted successfully.";
    }
}

// Fetch all data for display
$experiences = $db->query("SELECT * FROM `surprise_experiences` ORDER BY `display_order` ASC, `id` ASC")->fetchAll(PDO::FETCH_ASSOC);
$upgrades = $db->query("SELECT * FROM `surprise_upgrades` ORDER BY `display_order` ASC, `id` ASC")->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Surprise Experience Builder & Google Maps Pincodes";
require_once 'admin_header.php';
?>

<div class="space-y-8">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-200 pb-6">
        <div>
            <h1 class="text-2xl sm:text-3xl font-serif font-bold text-slate-900">Surprise Experience & Area Control</h1>
            <p class="text-slate-500 text-sm mt-1">Manage base surprise packages, upgrades, pricing, and Bengaluru
                delivery pincodes.</p>
        </div>
        <div class="flex items-center gap-3">
            <span
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-200">
                ✨ Dynamic Configuration Active
            </span>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if ($message): ?>
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl text-sm font-medium">
            <?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 rounded-xl text-sm font-medium">
            <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <!-- SECTION 1: BASE SURPRISE EXPERIENCES -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 space-y-6">
        <div
            class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 border-b border-slate-100 pb-4">
            <div>
                <h3 class="text-lg font-serif font-bold text-slate-900">1. Base Surprise Experiences</h3>
                <p class="text-xs text-slate-500">Add or edit base surprise packages, titles, pricing, badges and
                    images.</p>
            </div>
            <button onclick="openExpModal()"
                class="py-2 px-4 gold-gradient text-slate-950 font-bold rounded-xl text-xs shadow-md hover:shadow-lg transition-all">
                + Add New Experience
            </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <?php foreach ($experiences as $exp): ?>
                <?php
                $feats = json_decode($exp['features'], true);
                $featsList = is_array($feats) ? implode(', ', $feats) : $exp['features'];
                ?>
                <div
                    class="border border-slate-200 rounded-xl p-5 bg-slate-50/50 flex flex-col justify-between space-y-4 hover:border-amber-400 transition-colors">
                    <div class="space-y-3">
                        <div class="flex justify-between items-start gap-2">
                            <div>
                                <span
                                    class="px-2 py-0.5 text-[10px] uppercase font-bold tracking-wider rounded bg-slate-200 text-slate-700">Order
                                    #<?php echo $exp['display_order']; ?></span>
                                <?php if ($exp['badge']): ?>
                                    <span
                                        class="px-2 py-0.5 text-[10px] font-bold rounded bg-amber-100 text-amber-800 border border-amber-200 ml-1"><?php echo htmlspecialchars($exp['badge']); ?></span>
                                <?php endif; ?>
                                <h4 class="font-serif font-bold text-base text-slate-900 mt-1">
                                    <?php echo htmlspecialchars($exp['title']); ?></h4>
                            </div>
                            <span
                                class="text-lg font-bold text-amber-600">₹<?php echo number_format($exp['base_price']); ?></span>
                        </div>
                        <p class="text-xs text-slate-500 italic"><?php echo htmlspecialchars($exp['subtitle']); ?></p>
                        <p class="text-xs text-slate-600 line-clamp-2"><?php echo htmlspecialchars($exp['description']); ?>
                        </p>

                        <div class="flex items-center gap-3 pt-2">
                            <img src="<?php echo htmlspecialchars($exp['image']); ?>"
                                class="w-14 h-14 object-cover rounded-lg border border-slate-200" alt="Exp Image">
                            <div class="text-[11px] text-slate-500 min-w-0 flex-1">
                                <strong class="text-slate-700 block mb-0.5">Features:</strong>
                                <span class="truncate block"><?php echo htmlspecialchars($featsList); ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between pt-3 border-t border-slate-200/60 text-xs">
                        <span
                            class="<?php echo $exp['is_active'] ? 'text-emerald-600 font-semibold' : 'text-slate-400'; ?>">
                            <?php echo $exp['is_active'] ? '● Active' : '○ Disabled'; ?>
                        </span>
                        <div class="flex items-center gap-2">
                            <button onclick='editExp(<?php echo json_encode($exp); ?>)'
                                class="px-3 py-1 bg-slate-800 text-white rounded-lg font-medium hover:bg-slate-900">Edit</button>
                            <form method="POST" onsubmit="return confirm('Delete this experience?');" class="inline">
                                <input type="hidden" name="action" value="delete_experience">
                                <input type="hidden" name="id" value="<?php echo $exp['id']; ?>">
                                <button type="submit"
                                    class="px-3 py-1 bg-rose-50 text-rose-600 rounded-lg font-medium hover:bg-rose-100">Delete</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- SECTION 2: EXPERIENCE UPGRADES -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 space-y-6">
        <div
            class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 border-b border-slate-100 pb-4">
            <div>
                <h3 class="text-lg font-serif font-bold text-slate-900">2. Experience Upgrades & Add-ons</h3>
                <p class="text-xs text-slate-500">Configure optional add-on experiences, icons, descriptions & prices.
                </p>
            </div>
            <button onclick="openUpgModal()"
                class="py-2 px-4 gold-gradient text-slate-950 font-bold rounded-xl text-xs shadow-md hover:shadow-lg transition-all">
                + Add New Upgrade
            </button>
        </div>

        <div class="table-wrapper">
            <table class="w-full text-left text-sm">
                <thead
                    class="bg-slate-100/70 text-slate-600 uppercase text-[11px] font-bold tracking-wider border-b border-slate-200">
                    <tr>
                        <th class="py-3 px-4">Order</th>
                        <th class="py-3 px-4">Icon</th>
                        <th class="py-3 px-4">Upgrade Name</th>
                        <th class="py-3 px-4">Description</th>
                        <th class="py-3 px-4">Price</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($upgrades as $upg): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="py-3.5 px-4 text-xs font-mono font-bold text-slate-500">
                                #<?php echo $upg['display_order']; ?></td>
                            <td class="py-3.5 px-4 font-semibold text-amber-600 text-xs">
                                <?php echo htmlspecialchars($upg['icon']); ?></td>
                            <td class="py-3.5 px-4 font-bold text-slate-900"><?php echo htmlspecialchars($upg['name']); ?>
                            </td>
                            <td class="py-3.5 px-4 text-xs text-slate-600 max-w-xs">
                                <?php echo htmlspecialchars($upg['description']); ?></td>
                            <td class="py-3.5 px-4 font-bold text-amber-600">₹<?php echo number_format($upg['price']); ?>
                            </td>
                            <td class="py-3.5 px-4 text-xs">
                                <span
                                    class="<?php echo $upg['is_active'] ? 'text-emerald-600 font-semibold' : 'text-slate-400'; ?>">
                                    <?php echo $upg['is_active'] ? 'Active' : 'Disabled'; ?>
                                </span>
                            </td>
                            <td class="py-3.5 px-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button onclick='editUpg(<?php echo json_encode($upg); ?>)'
                                        class="px-2.5 py-1 bg-slate-800 text-white rounded text-xs font-medium">Edit</button>
                                    <form method="POST" onsubmit="return confirm('Delete this upgrade?');" class="inline">
                                        <input type="hidden" name="action" value="delete_upgrade">
                                        <input type="hidden" name="id" value="<?php echo $upg['id']; ?>">
                                        <button type="submit"
                                            class="px-2.5 py-1 bg-rose-50 text-rose-600 rounded text-xs font-medium">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>


</div>

<!-- MODAL: ADD/EDIT EXPERIENCE -->
<div id="expModal"
    class="hidden fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="bg-white max-w-lg w-full rounded-2xl p-6 shadow-2xl space-y-4 max-h-[90vh] overflow-y-auto">
        <h3 id="expModalTitle" class="text-lg font-serif font-bold text-slate-900 border-b border-slate-100 pb-3">Add
            Experience</h3>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="save_experience">
            <input type="hidden" id="exp_id" name="id" value="">

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Title</label>
                <input type="text" id="exp_title" name="title" required
                    class="w-full px-4 py-2 rounded-xl border border-slate-300 text-sm outline-none focus:ring-2 focus:ring-amber-500">
            </div>
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Subtitle</label>
                <input type="text" id="exp_subtitle" name="subtitle"
                    class="w-full px-4 py-2 rounded-xl border border-slate-300 text-sm outline-none focus:ring-2 focus:ring-amber-500">
            </div>
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Description</label>
                <textarea id="exp_description" name="description" rows="3"
                    class="w-full px-4 py-2 rounded-xl border border-slate-300 text-sm outline-none focus:ring-2 focus:ring-amber-500"></textarea>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Base Price
                        (₹)</label>
                    <input type="number" step="0.01" id="exp_price" name="base_price" required
                        class="w-full px-4 py-2 rounded-xl border border-slate-300 text-sm outline-none focus:ring-2 focus:ring-amber-500">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Badge (e.g. Top
                        Rated)</label>
                    <input type="text" id="exp_badge" name="badge"
                        class="w-full px-4 py-2 rounded-xl border border-slate-300 text-sm outline-none focus:ring-2 focus:ring-amber-500">
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Image URL</label>
                <input type="url" id="exp_image" name="image" required
                    class="w-full px-4 py-2 rounded-xl border border-slate-300 text-sm outline-none focus:ring-2 focus:ring-amber-500">
            </div>
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Features List (1 per
                    line)</label>
                <textarea id="exp_features" name="features" rows="4" placeholder="2-Hour Setup&#10;Rose Petal Carpet"
                    class="w-full px-4 py-2 rounded-xl border border-slate-300 text-sm outline-none focus:ring-2 focus:ring-amber-500"></textarea>
            </div>
            <div class="grid grid-cols-2 gap-3 items-center">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Display
                        Order</label>
                    <input type="number" id="exp_order" name="display_order" value="1"
                        class="w-full px-4 py-2 rounded-xl border border-slate-300 text-sm outline-none focus:ring-2 focus:ring-amber-500">
                </div>
                <div class="pt-4">
                    <label class="flex items-center gap-2 cursor-pointer font-semibold text-sm text-slate-700">
                        <input type="checkbox" id="exp_active" name="is_active" value="1" checked
                            class="w-4 h-4 text-amber-600 rounded"> Active on Site
                    </label>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
                <button type="button" onclick="closeExpModal()"
                    class="px-4 py-2 text-slate-600 text-sm font-semibold">Cancel</button>
                <button type="submit"
                    class="px-5 py-2 gold-gradient text-slate-950 font-bold rounded-xl text-sm shadow-md">Save
                    Experience</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: ADD/EDIT UPGRADE -->
<div id="upgModal"
    class="hidden fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="bg-white max-w-md w-full rounded-2xl p-6 shadow-2xl space-y-4">
        <h3 id="upgModalTitle" class="text-lg font-serif font-bold text-slate-900 border-b border-slate-100 pb-3">Add
            Upgrade</h3>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="save_upgrade">
            <input type="hidden" id="upg_id" name="id" value="">

            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Upgrade Name</label>
                <input type="text" id="upg_name" name="name" required
                    class="w-full px-4 py-2 rounded-xl border border-slate-300 text-sm outline-none focus:ring-2 focus:ring-amber-500">
            </div>
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Description</label>
                <textarea id="upg_description" name="description" rows="2"
                    class="w-full px-4 py-2 rounded-xl border border-slate-300 text-sm outline-none focus:ring-2 focus:ring-amber-500"></textarea>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Price
                        (₹)</label>
                    <input type="number" step="0.01" id="upg_price" name="price" required
                        class="w-full px-4 py-2 rounded-xl border border-slate-300 text-sm outline-none focus:ring-2 focus:ring-amber-500">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Icon
                        Name</label>
                    <select id="upg_icon" name="icon"
                        class="w-full px-4 py-2 rounded-xl border border-slate-300 text-sm outline-none focus:ring-2 focus:ring-amber-500 bg-white">
                        <option value="Music">Music (Live Singer)</option>
                        <option value="Camera">Camera (Photographer)</option>
                        <option value="Gift">Gift (Keepsake Box)</option>
                        <option value="Sparkles">Sparkles (Pyro)</option>
                        <option value="Heart">Heart (Balloons)</option>
                        <option value="Star">Star (General)</option>
                    </select>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3 items-center">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">Display
                        Order</label>
                    <input type="number" id="upg_order" name="display_order" value="1"
                        class="w-full px-4 py-2 rounded-xl border border-slate-300 text-sm outline-none focus:ring-2 focus:ring-amber-500">
                </div>
                <div class="pt-4">
                    <label class="flex items-center gap-2 cursor-pointer font-semibold text-sm text-slate-700">
                        <input type="checkbox" id="upg_active" name="is_active" value="1" checked
                            class="w-4 h-4 text-amber-600 rounded"> Active on Site
                    </label>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
                <button type="button" onclick="closeUpgModal()"
                    class="px-4 py-2 text-slate-600 text-sm font-semibold">Cancel</button>
                <button type="submit"
                    class="px-5 py-2 gold-gradient text-slate-950 font-bold rounded-xl text-sm shadow-md">Save
                    Upgrade</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openExpModal() {
        document.getElementById('expModalTitle').innerText = 'Add New Experience';
        document.getElementById('exp_id').value = '';
        document.getElementById('exp_title').value = '';
        document.getElementById('exp_subtitle').value = '';
        document.getElementById('exp_description').value = '';
        document.getElementById('exp_price').value = '';
        document.getElementById('exp_badge').value = '';
        document.getElementById('exp_image').value = '';
        document.getElementById('exp_features').value = '';
        document.getElementById('exp_order').value = '1';
        document.getElementById('exp_active').checked = true;
        document.getElementById('expModal').classList.remove('hidden');
    }

    function editExp(exp) {
        document.getElementById('expModalTitle').innerText = 'Edit Experience';
        document.getElementById('exp_id').value = exp.id;
        document.getElementById('exp_title').value = exp.title || '';
        document.getElementById('exp_subtitle').value = exp.subtitle || '';
        document.getElementById('exp_description').value = exp.description || '';
        document.getElementById('exp_price').value = exp.base_price || '';
        document.getElementById('exp_badge').value = exp.badge || '';
        document.getElementById('exp_image').value = exp.image || '';

        let feats = [];
        try { feats = JSON.parse(exp.features); } catch (e) { feats = [exp.features]; }
        document.getElementById('exp_features').value = Array.isArray(feats) ? feats.join('\n') : '';

        document.getElementById('exp_order').value = exp.display_order || 1;
        document.getElementById('exp_active').checked = exp.is_active == 1;
        document.getElementById('expModal').classList.remove('hidden');
    }

    function closeExpModal() {
        document.getElementById('expModal').classList.add('hidden');
    }

    function openUpgModal() {
        document.getElementById('upgModalTitle').innerText = 'Add New Upgrade';
        document.getElementById('upg_id').value = '';
        document.getElementById('upg_name').value = '';
        document.getElementById('upg_description').value = '';
        document.getElementById('upg_price').value = '';
        document.getElementById('upg_icon').value = 'Sparkles';
        document.getElementById('upg_order').value = '1';
        document.getElementById('upg_active').checked = true;
        document.getElementById('upgModal').classList.remove('hidden');
    }

    function editUpg(upg) {
        document.getElementById('upgModalTitle').innerText = 'Edit Upgrade';
        document.getElementById('upg_id').value = upg.id;
        document.getElementById('upg_name').value = upg.name || '';
        document.getElementById('upg_description').value = upg.description || '';
        document.getElementById('upg_price').value = upg.price || '';
        document.getElementById('upg_icon').value = upg.icon || 'Sparkles';
        document.getElementById('upg_order').value = upg.display_order || 1;
        document.getElementById('upg_active').checked = upg.is_active == 1;
        document.getElementById('upgModal').classList.remove('hidden');
    }

    function closeUpgModal() {
        document.getElementById('upgModal').classList.add('hidden');
    }
</script>

<?php require_once 'admin_footer.php'; ?>