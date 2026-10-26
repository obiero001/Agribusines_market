<?php
// 1. Start Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Database Connection (Built directly into the page to guarantee $pdo exists)
$host = 'localhost';
$db   = 'agriconnect_db'; 
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("<div style='background: #ffebee; color: #c62828; padding: 20px; font-family: sans-serif; border: 1px solid #ef9a9a; margin: 20px; border-radius: 5px;'>
            <h3>⚠️ Database Connection Failed</h3>
            <p>" . htmlspecialchars($e->getMessage()) . "</p>
            <p>Please make sure your database <strong>" . $db . "</strong> exists in XAMPP phpMyAdmin.</p>
         </div>");
}

$page_title = "Fresh Produce Catalogue";

// 3. Include your helper functions and header
require_once 'functions.php';
include 'header.php';

// Fetch search and category filter parameters
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$selected_category = isset($_GET['category']) ? intval($_GET['category']) : 0;

// Fetch all categories for filter options & finding active category name
$categories_stmt = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
$categories = $categories_stmt->fetchAll();

// Find selected category name for UI display
$selected_category_name = '';
if ($selected_category > 0) {
    foreach ($categories as $cat) {
        if ($cat['id'] === $selected_category) {
            $selected_category_name = $cat['name'];
            break;
        }
    }
}

// Build dynamic SQL query for filtering approved produce
$sql = "SELECT p.*, c.name AS category_name, u.name AS farmer_name 
        FROM produce p 
        JOIN categories c ON p.category_id = c.id 
        JOIN users u ON p.farmer_id = u.id 
        WHERE p.status = 'Approved'";

$params = [];

if (!empty($search_query)) {
    $sql .= " AND (p.name LIKE ? OR p.location LIKE ? OR u.name LIKE ?)";
    $term = "%{$search_query}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

if ($selected_category > 0) {
    $sql .= " AND p.category_id = ?";
    $params[] = $selected_category;
}

$sql .= " ORDER BY p.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$produce_items = $stmt->fetchAll();
?>

<div class="container" style="margin-top: 2rem; margin-bottom: 4rem;">

    <!-- Hero Banner & Filter Section -->
    <div style="background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%); color: white; padding: 2.5rem 2rem; border-radius: var(--radius); margin-bottom: 2rem; box-shadow: var(--shadow-md);">
        <h1 style="font-size: 2.2rem; margin-bottom: 0.5rem; font-weight: 700;">Fresh Harvest Catalogue</h1>
        <p style="opacity: 0.95; font-size: 1.05rem; max-width: 750px;">
            Buy directly from verified smallholder farmers across Kiambu, Murang'a, Meru, Nakuru, and Kisii counties. Fresh, traceable, and fair prices.
        </p>

        <!-- Search & Category Filter Form -->
        <form method="GET" action="catalogue.php" style="display: flex; gap: 1rem; flex-wrap: wrap; margin-top: 1.5rem;">
            <input type="text" name="search" value="<?= htmlspecialchars($search_query) ?>" placeholder="Search by crop name, farmer, or county..." style="flex: 2; min-width: 250px; padding: 0.75rem 1rem; border: none; border-radius: var(--radius); font-size: 0.95rem;">
            
            <select name="category" style="flex: 1; min-width: 180px; padding: 0.75rem 1rem; border: none; border-radius: var(--radius); font-size: 0.95rem;">
                <option value="0">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= $selected_category == $cat['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="btn btn-accent" style="padding: 0.75rem 1.8rem; font-size: 0.95rem; font-weight: 600;">
                🔍 Filter
            </button>

            <?php if (!empty($search_query) || $selected_category > 0): ?>
                <a href="catalogue.php" class="btn btn-outline" style="background: rgba(255,255,255,0.2); color: white; border-color: rgba(255,255,255,0.4); padding: 0.75rem 1.2rem;">
                    Clear Filters
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Active Search Results Indicator -->
    <?php if (!empty($search_query) || $selected_category > 0): ?>
        <div style="margin-bottom: 1.5rem; color: var(--text-muted); font-size: 0.95rem;">
            Showing results for 
            <?php if (!empty($search_query)): ?> "<strong><?= htmlspecialchars($search_query) ?></strong>" <?php endif; ?>
            <?php if ($selected_category > 0): ?> in category "<strong><?= htmlspecialchars($selected_category_name) ?></strong>" <?php endif; ?>
            (<strong><?= count($produce_items) ?></strong> items found)
        </div>
    <?php endif; ?>

    <!-- Produce Grid Cards -->
    <div class="grid">
        <?php if (empty($produce_items)): ?>
            <div style="grid-column: 1 / -1; background: white; padding: 3rem; text-align: center; border-radius: var(--radius); border: 1px solid var(--gray-border);">
                <h3 style="color: var(--dark); margin-bottom: 0.5rem;">No Fresh Produce Matches Found</h3>
                <p style="color: var(--text-muted); margin-bottom: 1.5rem;">Try adjusting your search query or removing category filters.</p>
                <a href="catalogue.php" class="btn">View All Catalogue Items</a>
            </div>
        <?php else: ?>
            <?php foreach ($produce_items as $item): ?>
                <div class="card produce-card">
                    <a href="product.php?id=<?= $item['id'] ?>">
                        <img src="<?= htmlspecialchars($item['photo']) ?>" alt="<?= htmlspecialchars($item['name']) ?>" onerror="this.src='https://images.unsplash.com/photo-1542838132-92c53300491e?w=500'">
                    </a>
                    <div class="card-body">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.4rem;">
                            <span class="badge badge-confirmed"><?= htmlspecialchars($item['category_name']) ?></span>
                            <small style="color: var(--text-muted);">Stock: <?= $item['quantity'] ?> <?= htmlspecialchars($item['unit']) ?></small>
                        </div>

                        <h3 style="font-size: 1.2rem; font-weight: 700; margin-bottom: 0.3rem;">
                            <a href="product.php?id=<?= $item['id'] ?>" style="color: var(--dark); text-decoration: none;">
                                <?= htmlspecialchars($item['name']) ?>
                            </a>
                        </h3>

                        <p style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 1rem;">
                            👨‍🌾 <strong><?= htmlspecialchars($item['farmer_name']) ?></strong> | 📍 <?= htmlspecialchars($item['location']) ?>
                        </p>

                        <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--gray-border); padding-top: 0.8rem; margin-top: 0.5rem;">
                            <div>
                                <span style="font-size: 1.25rem; font-weight: 700; color: var(--primary);">
                                    <?= format_currency($item['price']) ?>
                                </span>
                                <small style="font-size: 0.8rem; color: var(--text-muted); display: block;">per <?= htmlspecialchars($item['unit']) ?></small>
                            </div>

                            <div style="display: flex; gap: 6px;">
                                <a href="product.php?id=<?= $item['id'] ?>" class="btn btn-outline" style="padding: 0.4rem 0.7rem; font-size: 0.85rem;" title="View Details">
                                    👁️ Details
                                </a>
                                <button class="btn btn-accent add-to-cart-btn" style="padding: 0.4rem 0.8rem; font-size: 0.85rem;" 
                                    data-id="<?= $item['id'] ?>" 
                                    data-name="<?= htmlspecialchars($item['name'], ENT_QUOTES) ?>" 
                                    data-price="<?= $item['price'] ?>">
                                    🛒 Add
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

<script src="main.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.add-to-cart-btn').forEach(button => {
        button.addEventListener('click', function() {
            const id = parseInt(this.getAttribute('data-id'));
            const name = this.getAttribute('data-name');
            const price = parseFloat(this.getAttribute('data-price'));
            
            if (typeof addToCart === 'function') {
                addToCart(id, name, price);
            }
        });
    });
});
</script>
<?php include 'footer.php'; ?>