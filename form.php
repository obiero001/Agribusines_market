<?php
declare(strict_types=1);

$page_title = "Post / Edit Produce Listing";
require_once 'functions.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Ensure user is logged in
if (empty($_SESSION['user_id'])) {
    header('Location: login.php?error=unauthorized');
    exit;
}

$userRole = $_SESSION['user_role'] ?? '';

// Restrict access to Farmers and Administrators
if (!in_array($userRole, ['Farmer', 'Administrator'], true)) {
    header('Location: index.php?error=unauthorized');
    exit;
}

$edit_mode = false;
$produce = [
    'produce_id' => '',
    'category_id' => '',
    'produce_name' => '',
    'description' => '',
    'price_per_unit' => '',
    'quantity_available' => '',
    'unit' => 'kg',
    'location' => '',
    'photo_url' => ''
];

// Check if editing an existing produce item
if (isset($_GET['id'])) {
    $produce_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    if ($produce_id && $produce_id > 0) {
        $stmt = $pdo->prepare("SELECT * FROM produce WHERE produce_id = ?");
        $stmt->execute([$produce_id]);
        $result = $stmt->fetch();

        if ($result) {
            // Ensure farmers can only edit their own produce unless they are Admin
            if ($userRole === 'Farmer' && (int)$result['farmer_id'] !== (int)$_SESSION['user_id']) {
                header('Location: farmer_dashboard.php?error=unauthorized');
                exit;
            }
            $edit_mode = true;
            $produce = $result;
        }
    }
}

// Fetch categories for dropdown selection
$categories_stmt = $pdo->query("SELECT category_id, category_name FROM categories ORDER BY category_name ASC");
$categories = $categories_stmt->fetchAll();

include 'header.php';
?>

<div class="container" style="max-width: 700px; margin-top: 2.5rem; margin-bottom: 4rem;">
    <!-- Back Navigation -->
    <div style="margin-bottom: 1.5rem;">
        <a href="<?= $userRole === 'Administrator' ? 'admin_dashboard.php' : 'farmer_dashboard.php' ?>" class="btn btn-outline" style="font-size: 0.88rem; padding: 0.4rem 0.8rem; text-decoration: none; border: 1px solid #cbd5e1; border-radius: 6px; color: #0f172a;">
            &larr; Back to Dashboard
        </a>
    </div>

    <!-- Form Container -->
    <div class="card card-body" style="padding: 2.5rem; background: white; border: 1px solid #e2e8f0; border-radius: 8px; border-top: 5px solid #1b5e20; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <h2 style="color: #0f172a; margin-bottom: 0.5rem;">
            <?= $edit_mode ? '✏️ Edit Produce Listing' : '🌱 Post New Agricultural Produce' ?>
        </h2>
        <p style="color: #64748b; font-size: 0.95rem; margin-bottom: 2rem;">
            Fill in the details below accurately to list your harvest for urban buyers across Kenya.
        </p>

        <!-- Error Alert Display if redirected with error -->
        <?php if (!empty($_GET['error'])): ?>
            <div style="background: #fee2e2; color: #991b1b; padding: 0.75rem 1rem; border-radius: 6px; margin-bottom: 1.5rem; font-size: 0.9rem; border: 1px solid #fecaca;">
                ⚠️ <strong>Notice:</strong> <?= htmlspecialchars(urldecode($_GET['error'])) ?>
            </div>
        <?php endif; ?>

        <!-- FORM WITH MULTIPART ENCTYPE FOR FILE UPLOADS -->
        <form action="process.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="<?= $edit_mode ? 'edit_produce' : 'add_produce' ?>">
            <?php if ($edit_mode): ?>
                <input type="hidden" name="id" value="<?= (int)$produce['produce_id'] ?>">
            <?php endif; ?>

            <!-- Category Selection -->
            <div class="form-group" style="margin-bottom: 1.25rem;">
                <label for="category_id" style="font-weight: 600; display: block; margin-bottom: 0.4rem; color: #334155;">Produce Category *</label>
                <select name="category_id" id="category_id" required style="width: 100%; padding: 0.75rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.95rem; background: #fff;">
                    <option value="">-- Select Category --</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= (int)$cat['category_id'] ?>" <?= ((int)$produce['category_id'] === (int)$cat['category_id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['category_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Produce Name -->
            <div class="form-group" style="margin-bottom: 1.25rem;">
                <label for="name" style="font-weight: 600; display: block; margin-bottom: 0.4rem; color: #334155;">Produce Name *</label>
                <input type="text" id="name" name="name" required maxlength="150" value="<?= htmlspecialchars($produce['produce_name']) ?>" placeholder="e.g., Fresh Grade A Tomatoes" style="width: 100%; padding: 0.75rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.95rem;">
            </div>

            <!-- Price and Quantity Grid -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem;">
                <div class="form-group">
                    <label for="price" style="font-weight: 600; display: block; margin-bottom: 0.4rem; color: #334155;">Price per Unit (KSh) *</label>
                    <input type="number" step="0.01" min="0.01" id="price" name="price" required value="<?= htmlspecialchars($produce['price_per_unit']) ?>" placeholder="e.g., 120.00" style="width: 100%; padding: 0.75rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.95rem;">
                </div>
                <div class="form-group">
                    <label for="quantity" style="font-weight: 600; display: block; margin-bottom: 0.4rem; color: #334155;">Quantity Available *</label>
                    <input type="number" step="0.1" min="0.1" id="quantity" name="quantity" required value="<?= htmlspecialchars($produce['quantity_available']) ?>" placeholder="e.g., 500" style="width: 100%; padding: 0.75rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.95rem;">
                </div>
            </div>

            <!-- Unit & Location Grid -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem;">
                <div class="form-group">
                    <label for="unit" style="font-weight: 600; display: block; margin-bottom: 0.4rem; color: #334155;">Unit of Measure *</label>
                    <select name="unit" id="unit" required style="width: 100%; padding: 0.75rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.95rem; background: #fff;">
                        <?php $currentUnit = $produce['unit'] ?? 'kg'; ?>
                        <option value="kg" <?= $currentUnit === 'kg' ? 'selected' : '' ?>>Kilograms (kg)</option>
                        <option value="bag" <?= $currentUnit === 'bag' ? 'selected' : '' ?>>Bag (90kg)</option>
                        <option value="crate" <?= $currentUnit === 'crate' ? 'selected' : '' ?>>Crate</option>
                        <option value="bunch" <?= $currentUnit === 'bunch' ? 'selected' : '' ?>>Bunch</option>
                        <option value="piece" <?= $currentUnit === 'piece' ? 'selected' : '' ?>>Piece</option>
                        <option value="litre" <?= $currentUnit === 'litre' ? 'selected' : '' ?>>Litre</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="location" style="font-weight: 600; display: block; margin-bottom: 0.4rem; color: #334155;">Farm Location / County *</label>
                    <input type="text" id="location" name="location" required maxlength="150" value="<?= htmlspecialchars($produce['location']) ?>" placeholder="e.g., Thika, Kiambu County" style="width: 100%; padding: 0.75rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.95rem;">
                </div>
            </div>

            <!-- Description -->
            <div class="form-group" style="margin-bottom: 1.25rem;">
                <label for="description" style="font-weight: 600; display: block; margin-bottom: 0.4rem; color: #334155;">Description / Quality Notes</label>
                <textarea id="description" name="description" rows="3" placeholder="Describe quality, organic status, harvest freshness..." style="width: 100%; padding: 0.75rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.95rem; resize: vertical;"><?= htmlspecialchars($produce['description'] ?? '') ?></textarea>
            </div>

            <!-- File Input / Produce Image -->
            <div class="form-group" style="margin-bottom: 2rem;">
                <label for="photo" style="font-weight: 600; display: block; margin-bottom: 0.4rem; color: #334155;">
                    Produce Image / Photo * <?= $edit_mode ? '(Leave blank to keep existing)' : '' ?>
                </label>
                <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp,image/avif" <?= $edit_mode ? '' : 'required' ?> style="width: 100%; padding: 0.5rem; border: 1px dashed #cbd5e1; border-radius: 6px; background: #f8fafc; font-size: 0.9rem;">
                <small style="color: #64748b; display: block; margin-top: 0.3rem; font-size: 0.78rem;">Accepted formats: JPG, PNG, WEBP, AVIF (Max size: 5MB).</small>
                
                <?php if ($edit_mode && !empty($produce['photo_url'])): ?>
                    <div style="margin-top: 0.75rem;">
                        <span style="font-size: 0.85rem; color: #64748b; display: block; margin-bottom: 0.2rem;">Current Image:</span>
                        <img src="uploads/<?= htmlspecialchars($produce['photo_url']) ?>" alt="Current Produce" style="width: 80px; height: 80px; object-fit: cover; border-radius: 6px; border: 1px solid #cbd5e1;">
                    </div>
                <?php endif; ?>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="btn btn-accent" style="width: 100%; padding: 0.85rem; background: #1b5e20; color: white; border: none; border-radius: 6px; font-weight: 700; cursor: pointer; font-size: 1rem;">
                <?= $edit_mode ? '💾 Update Produce Listing' : '🚀 Post Produce Listing' ?>
            </button>
        </form>
    </div>
</div>

<?php include 'footer.php'; ?>