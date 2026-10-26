<?php
ob_start(); // 1. Prevents any "headers already sent" errors
session_start();
require_once 'config.php';

// 2. Get and sanitize the product ID from the URL query string
$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($product_id <= 0) {
    header('Location: catalogue.php');
    exit;
}

// 3. Fetch specific produce details along with category and farmer information
$stmt =$pdo->prepare("SELECT p.*, c.name AS category_name, u.name AS farmer_name, u.email AS farmer_email 
                       FROM produce p 
                       JOIN categories c ON p.category_id = c.id 
                       JOIN users u ON p.farmer_id = u.id 
                       WHERE p.id = ? AND p.status = 'Approved'");
$stmt->execute([$product_id]);
$product =$stmt->fetch();

if (!$product) {
    // Redirect if product doesn't exist or is not approved yet
    header('Location: catalogue.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($product['name']) ?> - AgriConnect Kenya</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header>
        <a href="catalogue.php" class="logo">🌱 AgriConnect Kenya</a>
        <nav>
            <a href="catalogue.php">Catalogue</a>
            <a href="cart.php">Cart (<span id="cart-count">0</span>)</a>
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="logout.php">Logout (<?= htmlspecialchars($_SESSION['user_name']) ?>)</a>
            <?php else: ?>
                <a href="login.php">Login</a>
            <?php endif; ?>
        </nav>
    </header>

    <div class="container" style="max-width: 900px; margin-top: 3rem;">
        <div style="margin-bottom: 1rem;">
            <a href="catalogue.php" class="btn btn-outline" style="padding: 0.4rem 0.8rem; font-size: 0.85rem;">&larr; Back to Catalogue</a>
        </div>

        <div class="card" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 0; box-shadow: var(--shadow-md);">
            <!-- Produce Image -->
            <div>
                <img src="<?= htmlspecialchars($product['photo']) ?>" alt="<?= htmlspecialchars($product['name']) ?>" style="width: 100%; height: 100%; min-height: 300px; object-fit: cover;" onerror="this.src='https://images.unsplash.com/photo-1542838132-92c53300491e?w=500'">
            </div>

            <!-- Produce Information & Actions -->
            <div class="card-body" style="display: flex; flex-direction: column; justify-content: space-between; padding: 2rem;">
                <div>
                    <span class="badge badge-confirmed" style="margin-bottom: 0.5rem;"><?= htmlspecialchars($product['category_name']) ?></span>
                    <h1 style="font-size: 1.8rem; margin-bottom: 0.5rem; color: var(--dark);"><?= htmlspecialchars($product['name']) ?></h1>
                    
                    <div style="font-size: 1.5rem; font-weight: 700; color: var(--primary); margin-bottom: 1rem;">
                        KSh <?= number_format($product['price'], 2) ?> <small style="font-size: 0.9rem; font-weight: normal; color: var(--text-muted);">per <?= htmlspecialchars($product['unit']) ?></small>
                    </div>

                    <hr style="border: 0; border-top: 1px solid var(--gray-border); margin: 1rem 0;">

                    <ul style="list-style: none; display: flex; flex-direction: column; gap: 0.5rem; color: var(--text-muted); font-size: 0.95rem;">
                        <li><strong>📦 Stock Available:</strong> <?= $product['quantity'] ?> <?= htmlspecialchars($product['unit']) ?></li>
                        <li><strong>📍 Farm Origin / County:</strong> <?= htmlspecialchars($product['location']) ?> (Kenya)</li>
                        <li><strong>👨‍🌾 Smallholder Farmer:</strong> <?= htmlspecialchars($product['farmer_name']) ?></li>
                        <li><strong>📅 Harvest / Listing Date:</strong> <?= date('Y-m-d', strtotime($product['created_at'])) ?></li>
                    </ul>
                </div>

                <div style="margin-top: 2rem;">
                    <div style="display: flex; gap: 1rem; align-items: center;">
                        <input type="number" id="qty-input" value="1" min="1" max="<?= $product['quantity'] ?>" style="width: 80px; padding: 0.6rem; border: 1px solid var(--gray-border); border-radius: var(--radius); text-align: center; font-size: 1rem;">
                        <button class="btn btn-accent" style="flex: 1;" onclick="addToCartDetail(<?= $product['id'] ?>, '<?= addslashes($product['name']) ?>', <?=$product['price'] ?>)">
                            🛒 Add to Cart
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer>
        <p>&copy; 2026 AgriConnect Kenya. Connecting Smallholder Farmers to Urban Markets Directly.</p>
    </footer>

    <script src="main.js"></script>
    <script>
        function addToCartDetail(id, name, price) {
            let qty = parseInt(document.getElementById('qty-input').value) || 1;
            let cart = JSON.parse(localStorage.getItem('agri_cart')) || [];
            let existing = cart.find(item => item.id === id);
            
            if (existing) {
                existing.quantity += qty;
            } else {
                cart.push({ id, name, price, quantity: qty });
            }
            
            localStorage.setItem('agri_cart', JSON.stringify(cart));
            if (typeof updateCartBadge === 'function') {
                updateCartBadge();
            }
            alert(`${qty} ${name}(s) added to cart successfully!`);
        }
    </script>
</body>
</html>