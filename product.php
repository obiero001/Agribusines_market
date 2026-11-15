<?php
declare(strict_types=1);

ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php';
require_once 'functions.php';

// 1. Get and sanitize the product ID from the URL query string
$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($product_id <= 0) {
    header('Location: catalogue.php');
    exit;
}

// 2. Fetch specific produce details along with category and farmer information (DanFam Market schema)
try {
    $stmt = $pdo->prepare("
        SELECT p.*, c.category_name, u.full_name AS farmer_name, u.email AS farmer_email, 
               COALESCE(u.phone_number, 'Verified Farmer') AS farmer_phone 
        FROM produce p 
        JOIN categories c ON p.category_id = c.category_id 
        JOIN users u ON p.farmer_id = u.user_id 
        WHERE p.produce_id = ? AND p.status = 'Approved'
    ");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('[DanFam Product Error] ' . $e->getMessage());
    $product = false;
}

if (!$product) {
    header('Location: catalogue.php');
    exit;
}

$page_title = htmlspecialchars($product['produce_name']) . " - DanFam Market Kenya";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            background-color: #f8fafc;
            color: #0f172a;
            font-family: system-ui, -apple-system, sans-serif;
            margin: 0;
            padding: 0;
        }
        .product-wrapper {
            max-width: 1100px;
            margin: 2.5rem auto 4rem auto;
            padding: 0 1.5rem;
        }
        .breadcrumb-bar {
            margin-bottom: 1.5rem;
        }
        .product-card {
            background: #ffffff;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -4px rgba(0, 0, 0, 0.05);
            display: grid;
            grid-template-columns: 1fr 1fr;
            overflow: hidden;
            gap: 0;
        }
        @media (max-width: 768px) {
            .product-card {
                grid-template-columns: 1fr;
            }
        }
        .product-image-container {
            position: relative;
            background: #f1f5f9;
            min-height: 400px;
        }
        .product-image-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .category-floating-badge {
            position: absolute;
            top: 1rem;
            left: 1rem;
            background: rgba(27, 94, 32, 0.9);
            color: white;
            padding: 0.35rem 0.85rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            backdrop-filter: blur(4px);
        }
        .product-details-body {
            padding: 2.5rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .product-title {
            font-size: 2rem;
            font-weight: 800;
            color: #0f172a;
            margin: 0 0 0.5rem 0;
            line-height: 1.2;
        }
        .price-tag {
            font-size: 1.75rem;
            font-weight: 800;
            color: #1b5e20;
            margin-bottom: 1rem;
        }
        .price-tag small {
            font-size: 0.95rem;
            font-weight: 500;
            color: #64748b;
        }
        .description-box {
            color: #475569;
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 1.5rem;
            background: #f8fafc;
            padding: 1rem;
            border-radius: 8px;
            border: 1px solid #f1f5f9;
        }
        .specs-list {
            list-style: none;
            padding: 0;
            margin: 0 0 1.5rem 0;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        .specs-list li {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.92rem;
            color: #334155;
            padding-bottom: 0.5rem;
            border-bottom: 1px dashed #e2e8f0;
        }
        .specs-list li span:first-child {
            color: #64748b;
            font-weight: 600;
        }
        .farmer-trust-card {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .farmer-avatar {
            width: 45px;
            height: 45px;
            background: #1b5e20;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        .action-cluster {
            display: flex;
            gap: 1rem;
            align-items: center;
            margin-top: auto;
        }
        .qty-input-box {
            width: 90px;
            padding: 0.75rem;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            text-align: center;
            font-size: 1.05rem;
            font-weight: 600;
            color: #0f172a;
            background: white;
        }
        .add-cart-btn {
            flex: 1;
            background: #1b5e20;
            color: white;
            border: none;
            padding: 0.85rem 1.5rem;
            border-radius: 8px;
            font-size: 1.05rem;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s, transform 0.1s;
            box-shadow: 0 4px 6px -1px rgba(27, 94, 32, 0.2);
        }
        .add-cart-btn:hover {
            background: #144517;
        }
        .add-cart-btn:active {
            transform: scale(0.98);
        }
    </style>
</head>
<body>

    <!-- Header Navigation -->
    <header style="background: white; border-bottom: 1px solid #e2e8f0; padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center;">
        <a href="catalogue.php" class="logo" style="font-weight: 800; font-size: 1.3rem; color: #1b5e20; text-decoration: none;">DanFam Market</a>
        <nav style="display: flex; gap: 1.5rem; align-items: center;">
            <a href="catalogue.php" style="color: #334155; text-decoration: none; font-weight: 600;">Catalogue</a>
            <a href="cart.php" style="color: #334155; text-decoration: none; font-weight: 600;">Cart (<span id="cart-count">0</span>)</a>
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="logout.php" style="color: #d32f2f; text-decoration: none; font-weight: 600;">Logout (<?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?>)</a>
            <?php else: ?>
                <a href="login.php" style="background: #1b5e20; color: white; padding: 0.4rem 1rem; border-radius: 6px; text-decoration: none; font-weight: 600;">Login</a>
            <?php endif; ?>
        </nav>
    </header>

    <div class="product-wrapper">
        <!-- Breadcrumb / Back Navigation -->
        <div class="breadcrumb-bar">
            <a href="catalogue.php" class="btn btn-outline" style="display: inline-flex; align-items: center; gap: 6px; padding: 0.5rem 1rem; font-size: 0.9rem; text-decoration: none; color: #334155; background: white; border: 1px solid #cbd5e1; border-radius: 6px; font-weight: 600;">
                &larr; Back to Marketplace Catalogue
            </a>
        </div>

        <!-- Main Product Card Grid -->
        <div class="product-card">
            
            <!-- Left Column: Product Image -->
            <div class="product-image-container">
                <span class="category-floating-badge"><?= htmlspecialchars($product['category_name']) ?></span>
                <img src="uploads/<?= htmlspecialchars($product['photo_url'] ?? '') ?>" alt="<?= htmlspecialchars($product['produce_name']) ?>" onerror="this.src='https://images.unsplash.com/photo-1542838132-92c53300491e?w=600'">
            </div>

            <!-- Right Column: Details & Actions -->
            <div class="product-details-body">
                <div>
                    <h1 class="product-title"><?= htmlspecialchars($product['produce_name']) ?></h1>
                    
                    <div class="price-tag">
                        KSh <?= number_format((float)$product['price_per_unit'], 2) ?> 
                        <small>per <?= htmlspecialchars($product['unit'] ?? 'kg') ?></small>
                    </div>

                    <?php if (!empty($product['description'])): ?>
                        <div class="description-box">
                            <?= nl2br(htmlspecialchars($product['description'])) ?>
                        </div>
                    <?php endif; ?>

                    <!-- Specifications List -->
                    <ul class="specs-list">
                        <li>
                            <span>📦 Stock Available</span>
                            <strong><?= (float)$product['quantity_available'] ?> <?= htmlspecialchars($product['unit'] ?? 'kg') ?></strong>
                        </li>
                        <li>
                            <span>📍 Farm Origin</span>
                            <strong><?= htmlspecialchars($product['location'] ?? 'Kenya') ?></strong>
                        </li>
                        <li>
                            <span>📅 Harvest / Listing Date</span>
                            <strong><?= date('d M Y', strtotime($product['created_at'])) ?></strong>
                        </li>
                    </ul>

                    <!-- Farmer Verification Box -->
                    <div class="farmer-trust-card">
                        <div class="farmer-avatar">
                            <?= mb_substr(htmlspecialchars($product['farmer_name']), 0, 1) ?>
                        </div>
                        <div>
                            <span style="font-size: 0.75rem; text-transform: uppercase; color: #166534; font-weight: 700; display: block;">Verified Smallholder Farmer</span>
                            <strong style="color: #0f172a; font-size: 0.95rem;"><?= htmlspecialchars($product['farmer_name']) ?></strong><br>
                            <span style="color: #64748b; font-size: 0.82rem;"><?= htmlspecialchars($product['farmer_email']) ?></span>
                        </div>
                    </div>
                </div>

                <!-- Quantity & Add to Cart Cluster -->
                <div class="action-cluster">
                    <input type="number" id="qty-input" value="1" min="1" max="<?= max(1, (float)$product['quantity_available']) ?>" class="qty-input-box" title="Quantity">
                    <button class="add-cart-btn" onclick="addToCartDetail(<?= (int)$product['produce_id'] ?>, '<?= addslashes(htmlspecialchars($product['produce_name'])) ?>', <?= (float)$product['price_per_unit'] ?>)">
                        🛒 Add to Cart
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer style="background: white; border-top: 1px solid #e2e8f0; padding: 2rem; text-align: center; color: #64748b; font-size: 0.9rem;">
        <p style="margin: 0;">&copy; 2026 DanFam Market Kenya. Connecting Smallholder Farmers to Urban Markets Directly.</p>
    </footer>

    <script src="main.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            if (typeof updateCartBadge === 'function') {
                updateCartBadge();
            }
        });

        function addToCartDetail(id, name, price) {
            let qtyInput = document.getElementById('qty-input');
            let qty = parseFloat(qtyInput.value) || 1;
            let maxStock = parseFloat(qtyInput.max) || 9999;

            if (qty <= 0) {
                alert('Please enter a valid quantity.');
                return;
            }

            if (qty > maxStock) {
                alert(`Cannot add more than available stock (${maxStock}).`);
                return;
            }

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
            
            alert(`✅ Success! Added ${qty} of ${name} to your cart.`);
        }
    </script>
</body>
</html>