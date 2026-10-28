<?php
$page_title = "Produce Catalogue & Marketplace";
require_once 'functions.php';

// Fetch all approved categories for filtering
$categories_stmt = $pdo->query("SELECT category_id, category_name FROM categories ORDER BY category_name ASC");
$categories = $categories_stmt->fetchAll();

// Handle search and category filters
$category_filter = isset($_GET['category']) ? intval($_GET['category']) : 0;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

// Base query for approved produce listings (danfam_market schema)
$sql = "
    SELECT p.*, c.category_name, u.full_name AS farmer_name 
    FROM produce p 
    JOIN categories c ON p.category_id = c.category_id 
    JOIN users u ON p.farmer_id = u.user_id 
    WHERE p.status = 'Approved'
";
$params = [];

if ($category_filter > 0) {
    $sql .= " AND p.category_id = ?";
    $params[] = $category_filter;
}

if ($search_query !== '') {
    $sql .= " AND (p.produce_name LIKE ? OR p.location LIKE ? OR p.description LIKE ?)";
    // Escape SQL wildcard characters inside search string
    $escaped_search = '%' . addcslashes($search_query, '%_') . '%';
    $params[] = $escaped_search;
    $params[] = $escaped_search;
    $params[] = $escaped_search;
}

$sql .= " ORDER BY p.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

include 'header.php';
?>

<div class="container" style="max-width: 1200px; margin-top: 2.5rem; margin-bottom: 4rem;">
    <!-- Page Header & Search Bar -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="color: var(--dark, #0f172a); margin-bottom: 0.3rem;"> Fresh Agricultural Produce</h1>
            <p style="color: var(--text-muted, #64748b); font-size: 0.95rem;">Directly sourced from verified smallholder farmers across Kenya.</p>
        </div>

        <!-- Search Form -->
        <form action="catalogue.php" method="GET" style="display: flex; gap: 0.5rem; max-width: 400px; width: 100%;">
            <?php if ($category_filter > 0): ?>
                <input type="hidden" name="category" value="<?= $category_filter ?>">
            <?php endif; ?>
            <input type="text" name="search" value="<?= htmlspecialchars($search_query) ?>" placeholder="Search produce, county, or keyword..." style="flex: 1; padding: 0.6rem 0.8rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.9rem;">
            <button type="submit" class="btn btn-accent" style="padding: 0.6rem 1.2rem; background: #1b5e20; color: white; border: none; border-radius: 6px; font-weight: 600; cursor: pointer;">Search</button>
        </form>
    </div>

    <!-- Category Filters Navigation -->
    <div style="display: flex; gap: 0.5rem; overflow-x: auto; padding-bottom: 1rem; margin-bottom: 2rem; white-space: nowrap;">
        <a href="catalogue.php" style="padding: 0.5rem 1rem; border-radius: 20px; font-size: 0.9rem; text-decoration: none; background: <?= $category_filter === 0 && $search_query === '' ? '#1b5e20' : '#f8fafc' ?>; color: <?= $category_filter === 0 && $search_query === '' ? '#fff' : '#0f172a' ?>; border: 1px solid #cbd5e1;">
            All Produce
        </a>
        <?php foreach ($categories as $cat): ?>
            <a href="catalogue.php?category=<?= (int)$cat['category_id'] ?>" style="padding: 0.5rem 1rem; border-radius: 20px; font-size: 0.9rem; text-decoration: none; background: <?= $category_filter === (int)$cat['category_id'] ? '#1b5e20' : '#f8fafc' ?>; color: <?= $category_filter === (int)$cat['category_id'] ? '#fff' : '#0f172a' ?>; border: 1px solid #cbd5e1;">
                <?= htmlspecialchars($cat['category_name']) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Produce Grid -->
    <?php if (empty($products)): ?>
        <div class="card card-body" style="text-align: center; padding: 4rem 2rem; background: #f8fafc; border-radius: 8px;">
            <h3 style="color: #64748b; margin-bottom: 0.5rem;">No produce listings found</h3>
            <p style="color: #94a3b8; font-size: 0.95rem; margin-bottom: 1.5rem;">Try adjusting your search filters or check back later for new harvests.</p>
            <a href="catalogue.php" class="btn btn-outline" style="padding: 0.5rem 1.2rem; display: inline-block; text-decoration: none; border: 1px solid #cbd5e1; border-radius: 6px; color: #0f172a;">Clear Filters</a>
        </div>
    <?php else: ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.5rem;">
            <?php foreach ($products as $product): ?>
                <?php 
                    $image_src = !empty($product['photo_url']) ? 'uploads/' . htmlspecialchars($product['photo_url']) : 'https://images.unsplash.com/photo-1542838132-92c53300491e?w=500';
                ?>
                <div class="card" style="border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; background: white; display: flex; flex-direction: column; box-shadow: 0 1px 3px rgba(0,0,0,0.1); transition: transform 0.2s;">
                    
                    <!-- Produce Image Container -->
                    <div style="position: relative; height: 200px; background: #f1f5f9;">
                        <img src="<?= $image_src ?>" alt="<?= htmlspecialchars($product['produce_name']) ?>" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.src='https://images.unsplash.com/photo-1542838132-92c53300491e?w=500'">
                        <span style="position: absolute; top: 10px; left: 10px; background: rgba(27, 94, 32, 0.9); color: white; padding: 0.2rem 0.6rem; font-size: 0.75rem; border-radius: 4px; font-weight: 600;">
                            <?= htmlspecialchars($product['category_name']) ?>
                        </span>
                    </div>

                    <!-- Produce Card Body -->
                    <div class="card-body" style="padding: 1.25rem; display: flex; flex-direction: column; flex: 1; justify-content: space-between;">
                        <div>
                            <h3 style="font-size: 1.2rem; margin-bottom: 0.4rem; color: #0f172a;">
                                <a href="product.php?id=<?= (int)$product['produce_id'] ?>" style="text-decoration: none; color: inherit;">
                                    <?= htmlspecialchars($product['produce_name']) ?>
                                </a>
                            </h3>
                            <div style="font-size: 1.25rem; font-weight: 700; color: #1b5e20; margin-bottom: 0.5rem;">
                                KSh <?= number_format((float)$product['price_per_unit'], 2) ?> <small style="font-size: 0.8rem; font-weight: normal; color: #64748b;">per <?= htmlspecialchars($product['unit'] ?? 'kg') ?></small>
                            </div>
                            <p style="font-size: 0.88rem; color: #64748b; margin-bottom: 0.4rem;">
                                <strong>📍 Location:</strong> <?= htmlspecialchars($product['location']) ?>
                            </p>
                            <p style="font-size: 0.88rem; color: #64748b; margin-bottom: 1rem;">
                                <strong>Available Stock:</strong> <?= (float)$product['quantity_available'] ?> <?= htmlspecialchars($product['unit'] ?? 'kg') ?>
                            </p>
                        </div>

                        <!-- Action Buttons -->
                        <div style="display: flex; gap: 0.5rem; border-top: 1px solid #f1f5f9; padding-top: 1rem;">
                            <a href="product.php?id=<?= (int)$product['produce_id'] ?>" class="btn btn-outline" style="flex: 1; text-align: center; padding: 0.5rem; font-size: 0.85rem; text-decoration: none; border: 1px solid #cbd5e1; border-radius: 6px; color: #0f172a;">
                                View Details
                            </a>
                            <button onclick="addToCart(<?= (int)$product['produce_id'] ?>, '<?= htmlspecialchars($product['produce_name'], ENT_QUOTES, 'UTF-8') ?>', <?= (float)$product['price_per_unit'] ?>)" class="btn btn-accent" style="flex: 1; padding: 0.5rem; background: #1b5e20; color: white; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 0.85rem;">
                                🛒 Add to Cart
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<footer style="margin-top: 3rem; padding: 1.5rem; text-align: center; border-top: 1px solid #e2e8f0; background: #fff;">
    <p style="color: #64748b; font-size: 0.9rem;">&copy; 2026 DanFam Market Kenya. Connecting Smallholder Farmers to Urban Markets Directly.</p>
</footer>

<script src="main.js"></script>
<script>
    function addToCart(id, name, price) {
        let cart = JSON.parse(localStorage.getItem('agri_cart')) || [];
        let existing = cart.find(item => item.id === id);
        
        if (existing) {
            existing.quantity += 1;
        } else {
            cart.push({ id, name, price, quantity: 1 });
        }
        
        localStorage.setItem('agri_cart', JSON.stringify(cart));
        if (typeof updateCartBadge === 'function') {
            updateCartBadge();
        }
        alert(`${name} added to cart successfully!`);
    }
</script>

<?php include 'footer.php'; ?>
