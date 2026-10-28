<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// 1. AUTHENTICATION GUARD (Must execute before any output is sent)
if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'Farmer') {
    header('Location: login.php?error=unauthorized');
    exit();
}

require_once 'config.php';

$farmer_id = (int) $_SESSION['user_id'];
$farmer_name = $_SESSION['user_name'] ?? 'Producer';

// Fetch categories for the add produce dropdown
try {
    $categories_stmt = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
    $categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $categories = [];
}

// Fetch this farmer's produce listings
try {
    $produce_stmt = $pdo->prepare("
        SELECT p.*, c.name AS category_name 
        FROM produce p 
        JOIN categories c ON p.category_id = c.id 
        WHERE p.farmer_id = ? 
        ORDER BY p.id DESC
    ");
    $produce_stmt->execute([$farmer_id]);
    $my_produce = $produce_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $my_produce = [];
}

// Fetch orders containing this farmer's produce
try {
    $orders_stmt = $pdo->prepare("
        SELECT DISTINCT o.id AS order_id, o.status, o.created_at, o.shipping_address, o.total_amount, u.name AS buyer_name
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN produce p ON oi.produce_id = p.id
        JOIN users u ON o.buyer_id = u.id
        WHERE p.farmer_id = ?
        ORDER BY o.id DESC
    ");
    $orders_stmt->execute([$farmer_id]);
    $farmer_orders = $orders_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $farmer_orders = [];
}

// Calculate Farmer Statistics
$total_listed = count($my_produce);
$approved_count = count(array_filter($my_produce, fn($p) => $p['status'] === 'Approved'));
$pending_count = count(array_filter($my_produce, fn($p) => $p['status'] === 'Pending'));

// 2. NOW safely include the HTML header layout
require_once 'header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Farmer Dashboard - DanFam Market</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .dashboard-header {
            background: linear-gradient(135deg, #1b5e20 0%, #2e7d32 100%);
            color: white;
            padding: 2.5rem 2rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.2rem;
            margin-bottom: 2.5rem;
        }
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            text-align: center;
            border-top: 4px solid #1b5e20;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #1b5e20;
            margin-top: 0.3rem;
        }
        .section-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
    </style>
</head>
<body>

    <!-- PRIMARY NAVIGATION HEADER -->
    <header style="background: #ffffff; border-bottom: 1px solid #e2e8f0; position: sticky; top: 0; z-index: 1000; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <div style="max-width: 1200px; margin: 0 auto; padding: 0.85rem 1.5rem; display: flex; justify-content: space-between; align-items: center;">
            <a href="index.php" style="font-size: 1.35rem; font-weight: 800; color: #1b5e20; text-decoration: none;">DanFam Market</a>
            <nav style="display: flex; align-items: center; gap: 1.25rem;">
            </nav>
        </div>
    </header>

    <!-- FARMER SUB-HEADER NAVIGATION BANNER -->
    <section class="sub-header-banner" style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; padding: 0.65rem 0;">
        <div class="sub-header-wrapper" style="max-width: 1200px; margin: 0 auto; padding: 0 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <nav style="display: flex; gap: 0.5rem; align-items: center;">
                <a href="farmer_dashboard.php" style="padding: 0.45rem 0.9rem; border-radius: 6px; background: #1b5e20; color: #ffffff; font-weight: 600; font-size: 0.88rem; text-decoration: none;">Farmer Portal</a>
                <a href="index.php" style="padding: 0.45rem 0.9rem; border-radius: 6px; color: #475569; font-weight: 500; font-size: 0.88rem; text-decoration: none;">Browse Catalogue</a>
                <a href="orders.php" style="padding: 0.45rem 0.9rem; border-radius: 6px; color: #475569; font-weight: 500; font-size: 0.88rem; text-decoration: none;">📦 Buyer Orders</a>
            </nav>
            <div style="display: flex; align-items: center; gap: 0.85rem;">
                <div style="display: inline-flex; align-items: center; gap: 8px; background: #ffffff; border: 1px solid #cbd5e1; padding: 0.3rem 0.8rem; border-radius: 50px; font-size: 0.82rem; color: #334155;">
                    <span style="width: 8px; height: 8px; border-radius: 50%; background: #22c55e;"></span>
                    <span>Logged in as <strong><?= htmlspecialchars($farmer_name) ?></strong></span>
                </div>
                <a href="form.php" style="background: #f57c00; color: #ffffff; padding: 0.45rem 1rem; border-radius: 6px; font-size: 0.85rem; font-weight: 700; text-decoration: none;">+ Post Harvest</a>
            </div>
        </div>
    </section>

    <div class="container" style="max-width: 1200px; margin: 2rem auto; padding: 0 1.5rem;">
        
        <!-- Welcome Hero Banner -->
        <div class="dashboard-header">
            <h1 style="font-size: 2rem; margin-bottom: 0.5rem;">Welcome back, <?= htmlspecialchars($farmer_name) ?>! 🌾</h1>
            <p style="opacity: 0.9; font-size: 1.05rem;">Manage your farm produce listings, track marketplace approvals, and monitor buyer orders across Kenya.</p>
        </div>

        <?php if (isset($_GET['msg'])): ?>
            <div style="background: #d1fae5; color: #065f46; padding: 1rem 1.2rem; border-radius: 8px; margin-bottom: 2rem; font-weight: 600; border-left: 5px solid #1b5e20;">
                <?php
                    if ($_GET['msg'] === 'added') echo "✅ Produce listed successfully! It is now pending Administrator approval.";
                    elseif ($_GET['msg'] === 'invalid_input') echo "⚠️ Please fill in all required fields with valid numbers.";
                    else echo "✅ Action completed successfully.";
                ?>
            </div>
        <?php endif; ?>

        <!-- Quick Stats Overview -->
        <div class="stats-grid">
            <div class="stat-card">
                <p style="color: #64748b; font-size: 0.9rem; font-weight: 600;">Total Listings</p>
                <div class="stat-number"><?= $total_listed ?></div>
            </div>
            <div class="stat-card" style="border-top-color: #2e7d32;">
                <p style="color: #64748b; font-size: 0.9rem; font-weight: 600;">Approved Active</p>
                <div class="stat-number" style="color: #2e7d32;"><?= $approved_count ?></div>
            </div>
            <div class="stat-card" style="border-top-color: #ffb300;">
                <p style="color: #64748b; font-size: 0.9rem; font-weight: 600;">Pending Review</p>
                <div class="stat-number" style="color: #d97706;"><?= $pending_count ?></div>
            </div>
            <div class="stat-card" style="border-top-color: #0284c7;">
                <p style="color: #64748b; font-size: 0.9rem; font-weight: 600;">Customer Orders</p>
                <div class="stat-number" style="color: #0284c7;"><?= count($farmer_orders) ?></div>
            </div>
        </div>

        <!-- Table: My Produce Listings -->
        <div class="card card-body" style="margin-bottom: 3rem; padding: 2rem; background: white; border-radius: 8px; border: 1px solid #e2e8f0;">
            <h3 class="section-title"> My Produce Listings</h3>
            <div class="table-responsive">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 2px solid #e2e8f0; text-align: left;">
                            <th style="padding: 0.75rem;">Item</th>
                            <th style="padding: 0.75rem;">Category</th>
                            <th style="padding: 0.75rem;">Price</th>
                            <th style="padding: 0.75rem;">Available Stock</th>
                            <th style="padding: 0.75rem;">Location</th>
                            <th style="padding: 0.75rem;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($my_produce)): ?>
                            <tr><td colspan="6" style="text-align:center; padding: 2rem; color: #64748b;">You haven't posted any produce listings yet. Use the top menu to add your first harvest!</td></tr>
                        <?php else: ?>
                            <?php foreach ($my_produce as $p): ?>
                                <tr style="border-bottom: 1px solid #e2e8f0;">
                                    <td style="padding: 0.85rem 0.75rem;"><strong><?= htmlspecialchars($p['name']) ?></strong></td>
                                    <td style="padding: 0.85rem 0.75rem;"><?= htmlspecialchars($p['category_name']) ?></td>
                                    <td style="padding: 0.85rem 0.75rem;"><strong>KSh <?= number_format((float)$p['price'], 2) ?></strong> / <?= htmlspecialchars($p['unit']) ?></td>
                                    <td style="padding: 0.85rem 0.75rem;"><?= (float)$p['quantity'] ?> <?= htmlspecialchars($p['unit']) ?></td>
                                    <td style="padding: 0.85rem 0.75rem;">📍 <?= htmlspecialchars($p['location']) ?></td>
                                    <td style="padding: 0.85rem 0.75rem;">
                                        <span style="padding: 0.25rem 0.65rem; border-radius: 50px; font-size: 0.8rem; font-weight: 700; background: <?= $p['status'] === 'Approved' ? '#d1fae5; color: #065f46;' : '#fef3c7; color: #92400e;' ?>">
                                            <?= htmlspecialchars($p['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Table: Incoming Orders -->
        <div class="card card-body" style="margin-bottom: 2rem; padding: 2rem; background: white; border-radius: 8px; border: 1px solid #e2e8f0;">
            <h3 class="section-title">Orders Containing My Produce</h3>
            <div class="table-responsive">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 2px solid #e2e8f0; text-align: left;">
                            <th style="padding: 0.75rem;">Order ID</th>
                            <th style="padding: 0.75rem;">Buyer Name</th>
                            <th style="padding: 0.75rem;">Delivery Address</th>
                            <th style="padding: 0.75rem;">Date Placed</th>
                            <th style="padding: 0.75rem;">Lifecycle Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($farmer_orders)): ?>
                            <tr><td colspan="5" style="text-align:center; padding: 2rem; color: #64748b;">No customer orders received yet. Once buyers order your produce, they will appear here.</td></tr>
                        <?php else: ?>
                            <?php foreach ($farmer_orders as $ord): ?>
                                <tr style="border-bottom: 1px solid #e2e8f0;">
                                    <td style="padding: 0.85rem 0.75rem;"><strong>#AGRI-<?= str_pad((string)$ord['order_id'], 5, '0', STR_PAD_LEFT) ?></strong></td>
                                    <td style="padding: 0.85rem 0.75rem;"><?= htmlspecialchars($ord['buyer_name']) ?></td>
                                    <td style="padding: 0.85rem 0.75rem;"><?= htmlspecialchars($ord['shipping_address']) ?></td>
                                    <td style="padding: 0.85rem 0.75rem;"><small><?= date('d M Y, h:i A', strtotime($ord['created_at'])) ?></small></td>
                                    <td style="padding: 0.85rem 0.75rem;">
                                        <span style="padding: 0.25rem 0.65rem; border-radius: 50px; font-size: 0.8rem; font-weight: 700; background: <?= $ord['status'] === 'Delivered' ? '#d1fae5; color: #065f46;' : '#fef3c7; color: #92400e;' ?>">
                                            <?= htmlspecialchars($ord['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <?php require_once 'footer.php'; ?>
</body>
</html>