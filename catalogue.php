<?php

// 1. Start the session safely
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Database connection
$host = '127.0.0.1';
$db = 'danfam_market';
$user = 'root';
$pass = ''; 
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;port=3306;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    error_log('Catalogue database connection failed: ' . $e->getMessage());
    http_response_code(500);
    exit(
        '<div style="background:#ffebee;color:#c62828;padding:20px;
        font-family:Arial,sans-serif;border:1px solid #ef9a9a;
        margin:20px;border-radius:5px;">
            <h3>Database Connection Failed</h3>
            <p>Could not connect to the Danfam Marketplace database.</p>
            <p>Confirm that MySQL is running and that the database
            <strong>danfam_market</strong> is accessible.</p>
        </div>'
    );
}

// 3. Page settings and helper functions
$page_title = 'Fresh Produce Catalogue';

if (file_exists('functions.php')) {
    require_once 'functions.php';
} else {
    // Fallback format_currency if functions.php is missing
    function format_currency($amount) {
        return 'KSh ' . number_format($amount, 2);
    }
}

// 4. Read search and category filters
$search_query = trim((string) ($_GET['search'] ?? ''));
$selected_category = filter_input(
    INPUT_GET,
    'category',
    FILTER_VALIDATE_INT
);

$selected_category = ($selected_category !== false
    && $selected_category !== null
    && $selected_category > 0)
    ? $selected_category
    : 0;

// 5. Fetch categories
try {
    $categories_stmt = $pdo->query(
        'SELECT category_id AS id, category_name AS name FROM categories ORDER BY category_name ASC'
    );
    $categories = $categories_stmt->fetchAll();

    $selected_category_name = '';

    foreach ($categories as $cat) {
        if ((int) $cat['id'] === $selected_category) {
            $selected_category_name = $cat['name'];
            break;
        }
    }

    // 6. Build catalogue query mapped to danfam_market columns
    $sql = "
        SELECT 
            p.produce_id AS id,
            p.produce_name AS name,
            p.price_per_unit AS price,
            p.quantity_available AS quantity,
            p.unit,
            p.photo_url AS photo,
            p.location,
            c.category_name AS category_name,
            u.full_name AS farmer_name
        FROM produce AS p
        INNER JOIN categories AS c ON p.category_id = c.category_id
        INNER JOIN users AS u ON p.farmer_id = u.user_id
        WHERE p.status = 'Approved'
    ";

    $params = [];

    if ($search_query !== '') {
        $sql .= "
            AND (
                p.produce_name LIKE ?
                OR p.location LIKE ?
                OR u.full_name LIKE ?
            )
        ";

        $term = '%' . $search_query . '%';
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
    }

    if ($selected_category > 0) {
        $sql .= ' AND p.category_id = ?';
        $params[] = $selected_category;
    }

    $sql .= ' ORDER BY p.produce_id DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $produce_items = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log('Catalogue query failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Unable to load the catalogue. Please check the database tables and columns.');
}

// 7. Include the page header if available
if (file_exists('header.php')) {
    require_once 'header.php';
}
?>

<div class="container" style="margin-top:2rem;margin-bottom:4rem;">

    <!-- Hero banner and filters -->
    <div style="background:linear-gradient(135deg, #2e7d32 0%, #4caf50 100%);
                color:white;padding:2.5rem 2rem;border-radius:8px;
                margin-bottom:2rem;box-shadow:0 4px 6px rgba(0,0,0,0.1);">

        <h1 style="font-size:2.2rem;margin-bottom:0.5rem;font-weight:700;">
            Fresh Harvest Catalogue
        </h1>

        <p style="opacity:0.95;font-size:1.05rem;max-width:750px;">
            Buy directly from verified smallholder farmers across Kenya. Fresh, traceable, and fair prices.
        </p>

        <form method="GET" action="catalogue.php"
              style="display:flex;gap:1rem;flex-wrap:wrap;margin-top:1.5rem;">

            <input
                type="text"
                name="search"
                value="<?= htmlspecialchars($search_query, ENT_QUOTES, 'UTF-8') ?>"
                placeholder="Search by crop, farmer, or location..."
                aria-label="Search produce"
                style="flex:2;min-width:250px;padding:0.75rem 1rem;
                       border:none;border-radius:4px;font-size:0.95rem;"
            >

            <select
                name="category"
                aria-label="Filter by category"
                style="flex:1;min-width:180px;padding:0.75rem 1rem;
                       border:none;border-radius:4px;font-size:0.95rem;"
            >
                <option value="0">All Categories</option>

                <?php foreach ($categories as $cat): ?>
                    <option
                        value="<?= (int) $cat['id'] ?>"
                        <?= $selected_category === (int) $cat['id'] ? 'selected' : '' ?>
                    >
                        <?= htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="btn btn-accent"
                    style="padding:0.75rem 1.8rem;font-size:0.95rem;font-weight:600;background:#ff9800;color:white;border:none;border-radius:4px;cursor:pointer;">
                🔍 Filter
            </button>

            <?php if ($search_query !== '' || $selected_category > 0): ?>
                <a href="catalogue.php" class="btn btn-outline"
                   style="background:rgba(255,255,255,0.2);color:white;
                          border:1px solid rgba(255,255,255,0.4);padding:0.75rem 1.2rem;border-radius:4px;text-decoration:none;display:inline-block;">
                    Clear Filters
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Search results indicator -->
    <?php if ($search_query !== '' || $selected_category > 0): ?>
        <div style="margin-bottom:1.5rem;color:#666;font-size:0.95rem;">
            Showing results
            <?php if ($search_query !== ''): ?>
                for "<strong><?= htmlspecialchars($search_query, ENT_QUOTES, 'UTF-8') ?></strong>"
            <?php endif; ?>

            <?php if ($selected_category > 0): ?>
                in category
                "<strong><?= htmlspecialchars($selected_category_name, ENT_QUOTES, 'UTF-8') ?></strong>"
            <?php endif; ?>

            (<strong><?= count($produce_items) ?></strong> items found)
        </div>
    <?php endif; ?>

    <!-- Produce cards grid -->
    <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(280px, 1fr));gap:1.5rem;">

        <?php if (empty($produce_items)): ?>
            <div style="grid-column:1 / -1;background:white;padding:3rem;
                        text-align:center;border-radius:8px;
                        border:1px solid #ddd;">

                <h3 style="color:#333;margin-bottom:0.5rem;">
                    Sorry, No Fresh Produce Matches Found
                </h3>

                <p style="color:#666;margin-bottom:1.5rem;">
                    Try adjusting your search or removing category filters.
                </p>

                <a href="catalogue.php" style="background:#2e7d32;color:white;padding:0.6rem 1.2rem;border-radius:4px;text-decoration:none;">View All Catalogue Items</a>
            </div>

        <?php else: ?>

            <?php foreach ($produce_items as $item): ?>
                <?php
                $product_id = (int) $item['id'];
                $product_name = (string) $item['name'];
                $photo = trim((string) ($item['photo'] ?? ''));

                if ($photo === '') {
                    $photo = 'https://images.unsplash.com/photo-1542838132-92c53300491e?w=500';
                }
                ?>

                <div style="background:white;border:1px solid #ddd;border-radius:8px;overflow:hidden;box-shadow:0 2px 4px rgba(0,0,0,0.05);display:flex;flex-direction:column;justify-content:space-between;">

                    <a href="product.php?id=<?= $product_id ?>">
                        <img
                            src="<?= htmlspecialchars($photo, ENT_QUOTES, 'UTF-8') ?>"
                            alt="<?= htmlspecialchars($product_name, ENT_QUOTES, 'UTF-8') ?>"
                            loading="lazy"
                            style="width:100%;height:180px;object-fit:cover;"
                            onerror="this.onerror=null;this.src='https://images.unsplash.com/photo-1542838132-92c53300491e?w=500';"
                        >
                    </a>

                    <div style="padding:1.2rem;display:flex;flex-direction:column;gap:0.5rem;flex-grow:1;">

                        <div style="display:flex;justify-content:space-between;align-items:center;">
                            <span style="background:#e8f5e9;color:#2e7d32;padding:0.2rem 0.6rem;border-radius:12px;font-size:0.75rem;font-weight:600;">
                                <?= htmlspecialchars($item['category_name'], ENT_QUOTES, 'UTF-8') ?>
                            </span>

                            <small style="color:#666;font-size:0.85rem;">
                                Stock: <?= htmlspecialchars((string) $item['quantity'], ENT_QUOTES, 'UTF-8') ?>
                                <?= htmlspecialchars((string) $item['unit'], ENT_QUOTES, 'UTF-8') ?>
                            </small>
                        </div>

                        <h3 style="font-size:1.1rem;font-weight:700;margin:0;">
                            <a href="product.php?id=<?= $product_id ?>" style="color:#333;text-decoration:none;">
                                <?= htmlspecialchars($product_name, ENT_QUOTES, 'UTF-8') ?>
                            </a>
                        </h3>

                        <p style="color:#666;font-size:0.85rem;margin:0;">
                            👨‍🌾 <strong><?= htmlspecialchars($item['farmer_name'], ENT_QUOTES, 'UTF-8') ?></strong> 
                            | 📍 <?= htmlspecialchars((string) $item['location'], ENT_QUOTES, 'UTF-8') ?>
                        </p>

                        <div style="display:flex;justify-content:space-between;align-items:center;border-top:1px solid #eee;padding-top:0.8rem;margin-top:auto;">
                            <div>
                                <span style="font-size:1.15rem;font-weight:700;color:#2e7d32;">
                                    <?= htmlspecialchars((string) format_currency($item['price']), ENT_QUOTES, 'UTF-8') ?>
                                </span>
                                <small style="font-size:0.75rem;color:#666;display:block;">
                                    per <?= htmlspecialchars((string) $item['unit'], ENT_QUOTES, 'UTF-8') ?>
                                </small>
                            </div>

                            <div style="display:flex;gap:6px;">
                                <a href="product.php?id=<?= $product_id ?>"
                                   style="background:#f5f5f5;color:#333;padding:0.4rem 0.7rem;font-size:0.85rem;border-radius:4px;text-decoration:none;"
                                   title="View Details">
                                   👁️ Details
                                </a>

                                <button
                                    type="button"
                                    class="add-to-cart-btn"
                                    style="background:#ff9800;color:white;border:none;padding:0.4rem 0.8rem;font-size:0.85rem;border-radius:4px;cursor:pointer;"
                                    data-id="<?= $product_id ?>"
                                    data-name="<?= htmlspecialchars($product_name, ENT_QUOTES, 'UTF-8') ?>"
                                    data-price="<?= htmlspecialchars((string) $item['price'], ENT_QUOTES, 'UTF-8') ?>"
                                >
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
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.add-to-cart-btn').forEach(function (button) {
        button.addEventListener('click', function () {
            const id = Number.parseInt(this.dataset.id, 10);
            const name = this.dataset.name;
            const price = Number.parseFloat(this.dataset.price);

            if (typeof addToCart === 'function') {
                addToCart(id, name, price);
            } else {
                console.error('addToCart() was not found in main.js.');
                alert(name + ' added to cart!');
            }
        });
    });
});
</script>

<?php
if (file_exists('footer.php')) {
    require_once 'footer.php';
}
?>
