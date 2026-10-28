<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';
require_once 'functions.php';

// Role Guard: Require Farmer or Administrator access
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['Farmer', 'Administrator'], true)) {
    redirect('login.php?error=unauthorized');
}

$userId = (int) $_SESSION['user_id'];
$userRole = $_SESSION['user_role'];

// Fetch Categories for the dropdown select list (using danfam_market schema columns)
try {
    $cat_stmt = $pdo->query("SELECT category_id, category_name FROM categories ORDER BY category_name ASC");
    $categories = $cat_stmt->fetchAll();
} catch (PDOException $e) {
    $categories = [];
}

// Check if we are editing an existing produce item
$produceId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$produce = null;

if ($produceId > 0) {
    if ($userRole === 'Administrator') {
        $stmt = $pdo->prepare("SELECT * FROM produce WHERE produce_id = ?");
        $stmt->execute([$produceId]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM produce WHERE produce_id = ? AND farmer_id = ?");
        $stmt->execute([$produceId, $userId]);
    }
    $produce = $stmt->fetch();
}

$page_title = $produceId > 0 ? "Edit Produce Listing" : "Add Produce Listing";
include 'header.php';
?>

<div class="container" style="max-width: 650px; margin-top: 2rem; margin-bottom: 5rem;">
    <div class="card card-body" style="background: white; padding: 2rem; border-radius: 8px; border: 1px solid #e2e8f0; border-top: 4px solid #1b5e20;">
        
        <h2 style="color: #0f172a; margin-bottom: 0.3rem;"><?= $produceId > 0 ? 'Edit Produce Listing' : 'Post New Produce Listing' ?></h2>
        <p style="color: #64748b; font-size: 0.9rem; margin-bottom: 1.5rem;">Fill out the agricultural details below to list your harvest on DanFam Market.</p>

        <form action="process.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="<?= $produceId > 0 ? 'edit_produce' : 'add_produce' ?>">
            <?php if ($produceId > 0): ?>
                <input type="hidden" name="id" value="<?= $produceId ?>">
            <?php endif; ?>

            <!-- CATEGORY SELECT DROPDOWN -->
            <div style="margin-bottom: 1.2rem;">
                <label for="fieldCategory" style="font-weight: 600; font-size: 0.9rem; color: #334155; display: block; margin-bottom: 0.4rem;">Produce Category *</label>
                <select id="fieldCategory" name="category_id" required style="width: 100%; padding: 0.75rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.95rem; background: white;">
                    <option value="">-- Select Product Category --</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= (int)$cat['category_id'] ?>" <?= (isset($produce['category_id']) && (int)$produce['category_id'] === (int)$cat['category_id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['category_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- PRODUCE NAME -->
            <div style="margin-bottom: 1.2rem;">
                <label for="name" style="font-weight: 600; font-size: 0.9rem; color: #334155; display: block; margin-bottom: 0.4rem;">Produce Name *</label>
                <input type="text" id="name" name="name" required placeholder="e.g. Fresh Grade 1 Maize / Sukuma Wiki" style="width: 100%; padding: 0.75rem; border: 1px solid #cbd5e1; border-radius: 6px;" value="<?= htmlspecialchars($produce['produce_name'] ?? '') ?>">
            </div>

            <!-- DESCRIPTION -->
            <div style="margin-bottom: 1.2rem;">
                <label for="description" style="font-weight: 600; font-size: 0.9rem; color: #334155; display: block; margin-bottom: 0.4rem;">Description</label>
                <textarea id="description" name="description" rows="3" placeholder="Provide details about quality, organic farming, harvest date, etc." style="width: 100%; padding: 0.75rem; border: 1px solid #cbd5e1; border-radius: 6px;"><?= htmlspecialchars($produce['description'] ?? '') ?></textarea>
            </div>

            <!-- PRICE PER UNIT & QUANTITY -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.2rem;">
                <div>
                    <label for="price" style="font-weight: 600; font-size: 0.9rem; color: #334155; display: block; margin-bottom: 0.4rem;">Price per Unit (KSh) *</label>
                    <input type="number" step="0.01" min="0" id="price" name="price" required placeholder="e.g. 120.00" style="width: 100%; padding: 0.75rem; border: 1px solid #cbd5e1; border-radius: 6px;" value="<?= htmlspecialchars((string)($produce['price_per_unit'] ?? '')) ?>">
                </div>
                <div>
                    <label for="quantity" style="font-weight: 600; font-size: 0.9rem; color: #334155; display: block; margin-bottom: 0.4rem;">Quantity Available *</label>
                    <input type="number" step="0.01" min="0.01" id="quantity" name="quantity" required placeholder="e.g. 50" style="width: 100%; padding: 0.75rem; border: 1px solid #cbd5e1; border-radius: 6px;" value="<?= htmlspecialchars((string)($produce['quantity_available'] ?? '')) ?>">
                </div>
            </div>

            <!-- UNIT & LOCATION -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.2rem;">
                <div>
                    <label for="unit" style="font-weight: 600; font-size: 0.9rem; color: #334155; display: block; margin-bottom: 0.4rem;">Unit of Measure *</label>
                    <input type="text" id="unit" name="unit" required placeholder="e.g. Kg, Sacks, Crates, Litres" style="width: 100%; padding: 0.75rem; border: 1px solid #cbd5e1; border-radius: 6px;" value="<?= htmlspecialchars($produce['unit'] ?? 'kg') ?>">
                </div>
                <div>
                    <label for="location" style="font-weight: 600; font-size: 0.9rem; color: #334155; display: block; margin-bottom: 0.4rem;">Farm Location / Region *</label>
                    <input type="text" id="location" name="location" required placeholder="e.g. Thika, Kiambu County" style="width: 100%; padding: 0.75rem; border: 1px solid #cbd5e1; border-radius: 6px;" value="<?= htmlspecialchars($produce['location'] ?? '') ?>">
                </div>
            </div>

            <!-- PHOTO UPLOAD -->
            <div style="margin-bottom: 1.5rem;">
                <label for="photo" style="font-weight: 600; font-size: 0.9rem; color: #334155; display: block; margin-bottom: 0.4rem;">Produce Photo <?= $produceId > 0 ? '(Optional new image)' : '*' ?></label>
                <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp" <?= $produceId === 0 ? 'required' : '' ?> style="width: 100%; padding: 0.5rem; border: 1px solid #cbd5e1; border-radius: 6px; background: #f8fafc;">
                <small style="color: #64748b; display: block; margin-top: 0.3rem;">Accepted formats: JPG, PNG, WEBP (Max 5MB).</small>
            </div>

            <button type="submit" style="width: 100%; padding: 0.85rem; font-size: 1rem; font-weight: 700; background: #1b5e20; color: white; border: none; border-radius: 6px; cursor: pointer;">
                <?= $produceId > 0 ? '💾 Save Produce Changes' : '🚀 Submit Produce Listing' ?>
            </button>
        </form>
    </div>
</div>

<?php include 'footer.php'; ?>