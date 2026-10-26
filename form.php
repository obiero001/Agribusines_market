<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Authorization Guard: Only Farmers and Administrators can post/edit produce
if (empty($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['Farmer', 'Administrator'], true)) {
    header('Location: login.php?error=unauthorized');
    exit();
}

require_once 'config.php';

$userId = (int) $_SESSION['user_id'];
$userRole = $_SESSION['user_role'] ?? '';
$produceId = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : 0;

$produce = null;
if ($produceId > 0) {
    if ($userRole === 'Administrator') {
        $stmt = $pdo->prepare("SELECT * FROM produce WHERE id = ? LIMIT 1");
        $stmt->execute([$produceId]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM produce WHERE id = ? AND farmer_id = ? LIMIT 1");
        $stmt->execute([$produceId, $userId]);
    }
    $produce = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$produce) {
        header('Location: farmer_dashboard.php?error=not_found');
        exit();
    }
}

// Fetch categories for dropdown
try {
    $catStmt = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
    $categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $categories = [];
}

$page_title = $produceId > 0 ? "Edit Produce Listing" : "Post New Harvest";
require_once 'header.php';
?>

<div class="container" style="max-width: 900px; margin: 2.5rem auto 4rem auto; padding: 0 1rem;">

    <!-- Breadcrumb / Back Link -->
    <div style="margin-bottom: 1.5rem;">
        <a href="<?= $userRole === 'Administrator' ? 'admin_dashboard.php' : 'farmer_dashboard.php' ?>" style="color: #1b5e20; font-weight: 600; text-decoration: none;">
            &larr; Back to Dashboard
        </a>
    </div>

    <!-- Form Card Container -->
    <div class="card card-body" style="background: white; padding: 2.5rem; border-radius: 10px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
        
        <div style="border-bottom: 2px solid #e2e8f0; padding-bottom: 1rem; margin-bottom: 2rem;">
            <h2 style="color: #0f172a; margin: 0 0 0.25rem 0; font-size: 1.6rem;">
                <?= $produceId > 0 ? '✏️ Edit Produce Listing' : '🌱 Post New Harvest' ?>
            </h2>
            <p style="color: #64748b; font-size: 0.95rem; margin: 0;">
                <?= $produceId > 0 ? 'Update your harvest details and photos below.' : 'List your fresh farm produce for verified buyers across Kenya.' ?>
            </p>
        </div>

        <!-- Form with enctype for multipart file uploads -->
        <form id="produceForm" action="process.php" method="POST" enctype="multipart/form-data" style="display: flex; flex-direction: column; gap: 1.5rem;">
            
            <input type="hidden" name="action" value="<?= $produceId > 0 ? 'edit_produce' : 'add_produce' ?>">
            <?php if ($produceId > 0): ?>
                <input type="hidden" name="id" value="<?= (int)$produceId ?>">
            <?php endif; ?>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">

            <!-- Grid Row 1: Produce Name & Category -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem;">
                <div>
                    <label style="font-weight: 600; font-size: 0.9rem; color: #334155; display: block; margin-bottom: 0.4rem;">Produce Name *</label>
                    <input type="text" id="fieldName" name="name" required value="<?= htmlspecialchars($produce['name'] ?? '') ?>" placeholder="e.g. Fresh Hass Avocados" style="width: 100%; padding: 0.75rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.95rem;">
                </div>
                <div>
                    <label style="font-weight: 600; font-size: 0.9rem; color: #334155; display: block; margin-bottom: 0.4rem;">Category *</label>
                    <select id="fieldCategory" name="category_id" required style="width: 100%; padding: 0.75rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.95rem; background: white;">
                        <option value="">Select Category</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" data-name="<?= htmlspecialchars($cat['name']) ?>" <?= (isset($produce['category_id']) && $produce['category_id'] == $cat['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Grid Row 2: Price & Quantity -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem;">
                <div>
                    <label style="font-weight: 600; font-size: 0.9rem; color: #334155; display: block; margin-bottom: 0.4rem;">Price per Unit (KSh) *</label>
                    <input type="number" step="0.01" id="fieldPrice" name="price" required value="<?= htmlspecialchars($produce['price'] ?? '') ?>" placeholder="120.00" style="width: 100%; padding: 0.75rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.95rem;">
                </div>
                <div>
                    <label style="font-weight: 600; font-size: 0.9rem; color: #334155; display: block; margin-bottom: 0.4rem;">Available Quantity *</label>
                    <input type="number" step="0.1" id="fieldQuantity" name="quantity" required value="<?= htmlspecialchars($produce['quantity'] ?? '') ?>" placeholder="100" style="width: 100%; padding: 0.75rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.95rem;">
                </div>
            </div>

            <!-- Grid Row 3: Unit & Location -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem;">
                <div>
                    <label style="font-weight: 600; font-size: 0.9rem; color: #334155; display: block; margin-bottom: 0.4rem;">Unit of Measure *</label>
                    <select id="fieldUnit" name="unit" required style="width: 100%; padding: 0.75rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.95rem; background: white;">
                        <?php 
                        $units = ['kg' => 'Kilogram (kg)', 'bag' => 'Bag / Sack', 'crate' => 'Crate', 'piece' => 'Piece / Unit', 'tonne' => 'Tonne'];
                        $current_unit = $produce['unit'] ?? 'kg';
                        foreach ($units as $key => $label): 
                        ?>
                            <option value="<?= $key ?>" <?= $current_unit === $key ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="font-weight: 600; font-size: 0.9rem; color: #334155; display: block; margin-bottom: 0.4rem;">Farm Location (County / Region) *</label>
                    <input type="text" id="fieldLocation" name="location" required value="<?= htmlspecialchars($produce['location'] ?? '') ?>" placeholder="e.g. Kiambu, Limuru" style="width: 100%; padding: 0.75rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.95rem;">
                </div>
            </div>

            <!-- Description -->
            <div>
                <label style="font-weight: 600; font-size: 0.9rem; color: #334155; display: block; margin-bottom: 0.4rem;">Produce Description</label>
                <textarea id="fieldDesc" name="description" rows="3" placeholder="Provide details about quality, harvest freshness, or packaging..." style="width: 100%; padding: 0.75rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.95rem;"><?= htmlspecialchars($produce['description'] ?? '') ?></textarea>
            </div>

            <!-- Produce Photo Upload Field with Dropzone & Live Preview -->
            <div style="background: #f8fafc; padding: 1.5rem; border: 2px dashed #cbd5e1; border-radius: 8px; display: flex; flex-direction: column; gap: 0.75rem;">
                <label style="font-weight: 600; font-size: 0.9rem; color: #334155; display: block;">
                    📷 Produce Photo (Select a clear image of harvest)
                </label>
                
                <div class="file-upload-box" onclick="document.getElementById('fieldPhoto').click()" style="border: 2px dashed #cbd5e1; border-radius: 8px; padding: 20px; text-align: center; background-color: #ffffff; cursor: pointer; transition: background-color 0.2s;">
                    <span style="font-size: 1.2rem;">📂</span> <strong>Click to browse photo</strong>
                    <p style="font-size: 0.85rem; color: #64748b; margin-top: 4px;">Supported formats: JPG, PNG, WEBP (Max size: 5MB)</p>
                </div>

                <!-- Input name set to 'photo' to align correctly with process.php -->
                <input type="file" id="fieldPhoto" name="photo" accept="image/jpeg, image/png, image/webp" required style="display: none;" onchange="handleImageUpload(event)">
                
                <!-- Live Preview Thumbnail Container -->
                <div id="previewContainer" style="display: flex; flex-wrap: wrap; gap: 10px; margin-top: 10px;"></div>

                <?php if (!empty($produce['photo']) && $produce['photo'] !== 'default_produce.jpg'): ?>
                    <div style="margin-top: 0.5rem;">
                        <span style="font-size: 0.85rem; color: #64748b; font-weight: 600; display: block; margin-bottom: 4px;">Existing Uploaded Image:</span>
                        <div style="display: inline-flex; align-items: center; gap: 1rem; background: white; padding: 0.5rem 0.75rem; border-radius: 6px; border: 1px solid #e2e8f0;">
                            <img src="uploads/<?= htmlspecialchars($produce['photo']) ?>" alt="Current Photo" style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;">
                            <span style="font-size: 0.8rem; color: #334155;"><?= htmlspecialchars($produce['photo']) ?></span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Action Buttons: Preview & Submit -->
            <div style="display: flex; gap: 1rem; margin-top: 1rem;">
                <button type="button" onclick="openPreviewModal()" class="btn" style="flex: 1; padding: 0.9rem; background: #0284c7; color: white; border: none; border-radius: 6px; font-weight: 700; font-size: 1rem; cursor: pointer;">
                    👁️ Review Listing First
                </button>
                <button type="submit" class="btn btn-accent" style="flex: 1; padding: 0.9rem; background: #1b5e20; color: white; border: none; border-radius: 6px; font-weight: 700; font-size: 1rem; cursor: pointer;">
                    🚀 <?= $produceId > 0 ? 'Save Changes' : 'Submit Listing' ?>
                </button>
            </div>

        </form>

    </div>

</div>

<!-- PREVIEW MODAL CONTAINER -->
<div id="previewModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 2000; justify-content: center; align-items: center; padding: 1rem;">
    <div style="background: white; width: 100%; max-width: 600px; border-radius: 10px; overflow: hidden; box-shadow: 0 10px 25px rgba(0,0,0,0.2);">
        
        <div style="background: #1b5e20; color: white; padding: 1.25rem 1.5rem; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 1.2rem;">🔍 Harvest Review & Preview</h3>
            <button onclick="closePreviewModal()" style="background: none; border: none; color: white; font-size: 1.5rem; cursor: pointer;">&times;</button>
        </div>

        <div style="padding: 1.5rem; max-height: 70vh; overflow-y: auto;">
            <div style="text-align: center; margin-bottom: 1.5rem; background: #f8fafc; padding: 1rem; border-radius: 8px; border: 1px solid #e2e8f0;">
                <img id="modalImagePreview" src="uploads/default_produce.jpg" alt="Preview" style="max-height: 220px; max-width: 100%; object-fit: cover; border-radius: 6px;">
            </div>

            <table style="width: 100%; border-collapse: collapse; font-size: 0.95rem;">
                <tr style="border-bottom: 1px solid #e2e8f0;">
                    <td style="padding: 0.75rem; color: #64748b; font-weight: 600; width: 35%;">Produce Name:</td>
                    <td id="modalName" style="padding: 0.75rem; color: #0f172a; font-weight: 700;">-</td>
                </tr>
                <tr style="border-bottom: 1px solid #e2e8f0;">
                    <td style="padding: 0.75rem; color: #64748b; font-weight: 600;">Category:</td>
                    <td id="modalCategory" style="padding: 0.75rem; color: #0f172a;">-</td>
                </tr>
                <tr style="border-bottom: 1px solid #e2e8f0;">
                    <td style="padding: 0.75rem; color: #64748b; font-weight: 600;">Price:</td>
                    <td id="modalPrice" style="padding: 0.75rem; color: #1b5e20; font-weight: 700;">-</td>
                </tr>
                <tr style="border-bottom: 1px solid #e2e8f0;">
                    <td style="padding: 0.75rem; color: #64748b; font-weight: 600;">Quantity & Unit:</td>
                    <td id="modalQuantity" style="padding: 0.75rem; color: #0f172a;">-</td>
                </tr>
                <tr style="border-bottom: 1px solid #e2e8f0;">
                    <td style="padding: 0.75rem; color: #64748b; font-weight: 600;">Farm Location:</td>
                    <td id="modalLocation" style="padding: 0.75rem; color: #0f172a;">-</td>
                </tr>
                <tr>
                    <td style="padding: 0.75rem; color: #64748b; font-weight: 600;">Description:</td>
                    <td id="modalDesc" style="padding: 0.75rem; color: #334155;">-</td>
                </tr>
            </table>
        </div>

        <div style="background: #f8fafc; padding: 1rem 1.5rem; display: flex; justify-content: flex-end; gap: 1rem; border-top: 1px solid #e2e8f0;">
            <button type="button" onclick="closePreviewModal()" style="padding: 0.6rem 1.25rem; background: #e2e8f0; color: #334155; border: none; border-radius: 6px; font-weight: 600; cursor: pointer;">
                Back to Edit
            </button>
            <button type="button" onclick="submitForm()" style="padding: 0.6rem 1.5rem; background: #1b5e20; color: white; border: none; border-radius: 6px; font-weight: 700; cursor: pointer;">
                Confirm & Post Listing 🚀
            </button>
        </div>

    </div>
</div>

<!-- JAVASCRIPT FOR IMAGE PREVIEW & MODAL -->
<script>
    function handleImageUpload(event) {
        const file = event.target.files[0];
        const previewContainer = document.getElementById('previewContainer');
        previewContainer.innerHTML = ''; // Reset preview

        if (!file || !file.type.startsWith('image/')) return;

        const reader = new FileReader();
        reader.onload = function(e) {
            const card = document.createElement('div');
            card.style.cssText = "position: relative; width: 80px; height: 80px; border-radius: 6px; overflow: hidden; border: 1px solid #cbd5e1;";

            card.innerHTML = `
                <img src="${e.target.result}" alt="Preview" style="width: 100%; height: 100%; object-fit: cover;">
                <button type="button" onclick="removeSelectedImage()" style="position: absolute; top: 2px; right: 2px; background: rgba(239, 68, 68, 0.9); color: white; border: none; border-radius: 50%; width: 18px; height: 18px; font-size: 11px; cursor: pointer; display: flex; align-items: center; justify-content: center;">&times;</button>
            `;
            previewContainer.appendChild(card);
        };
        reader.readAsDataURL(file);
    }

    function removeSelectedImage() {
        document.getElementById('fieldPhoto').value = '';
        document.getElementById('previewContainer').innerHTML = '';
    }

    function openPreviewModal() {
        document.getElementById('modalName').innerText = document.getElementById('fieldName').value || 'Not provided';
        
        const catSelect = document.getElementById('fieldCategory');
        document.getElementById('modalCategory').innerText = catSelect.options[catSelect.selectedIndex]?.text || 'Not selected';

        const price = document.getElementById('fieldPrice').value;
        document.getElementById('modalPrice').innerText = price ? 'KSh ' + Number(price).toFixed(2) : 'Not provided';

        const qty = document.getElementById('fieldQuantity').value;
        const unit = document.getElementById('fieldUnit').value;
        document.getElementById('modalQuantity').innerText = qty ? `${qty} ${unit}` : 'Not provided';

        document.getElementById('modalLocation').innerText = document.getElementById('fieldLocation').value || 'Not provided';
        document.getElementById('modalDesc').innerText = document.getElementById('fieldDesc').value || 'No description provided.';

        const photoInput = document.getElementById('fieldPhoto');
        const modalImage = document.getElementById('modalImagePreview');

        if (photoInput.files && photoInput.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                modalImage.src = e.target.result;
            }
            reader.readAsDataURL(photoInput.files[0]);
        } else {
            <?php if (!empty($produce['photo'])): ?>
                modalImage.src = "uploads/<?= htmlspecialchars($produce['photo']) ?>";
            <?php else: ?>
                modalImage.src = "uploads/default_produce.jpg";
            <?php endif; ?>
        }

        document.getElementById('previewModal').style.display = 'flex';
    }

    function closePreviewModal() {
        document.getElementById('previewModal').style.display = 'none';
    }

    function submitForm() {
        document.getElementById('produceForm').submit();
    }
</script>

<?php require_once 'footer.php'; ?>