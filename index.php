<?php
require_once 'config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$isLoggedIn = !empty($_SESSION['user_id']);
$userRole = $_SESSION['user_role'] ?? '';
$userName = $_SESSION['user_name'] ?? 'User';

// Map error codes to clean, user-friendly messages
$errorMessages = [
    'invalid_action' => 'The action requested was invalid or unrecognized. Please try again.',
    'missing_action' => 'No action was specified for this request.',
    'operation_failed' => 'The operation could not be completed. Please check your inputs.',
    'unauthorized' => 'You must log in to access this feature.'
];

$successMessages = [
    'produce_posted' => 'Your harvest listing was successfully posted and is awaiting review.',
    'order_placed' => 'Your order has been placed successfully!',
    'produce_approved' => 'The produce listing has been approved successfully.'
];

$currentError = $_GET['error'] ?? '';
$currentSuccess = $_GET['success'] ?? '';

// Fetch approved produce listings for public browsing
try {
    $stmt = $pdo->query("
        SELECT p.*, c.name as category_name, u.name as farmer_name 
        FROM produce p 
        JOIN categories c ON p.category_id = c.id 
        JOIN users u ON p.farmer_id = u.id 
        WHERE p.status = 'Approved' 
        ORDER BY p.created_at DESC 
        LIMIT 12
    ");
    $listings = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $listings = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DanFam Market</title>
    <link rel="stylesheet" href="style.css">
    <style>
        :root {
            --primary: #1b5e20;
            --primary-dark: #0f3812;
            --accent: #f57c00;
            --bg-light: #f8fafc;
            --text-main: #0f172a;
            --text-muted: #64748b;
        }
        body { font-family: system-ui, -apple-system, sans-serif; margin: 0; background: var(--bg-light); color: var(--text-main); }
        header { background: white; border-bottom: 1px solid #e2e8f0; position: sticky; top: 0; z-index: 100; }
        .nav-container { max-width: 1200px; margin: 0 auto; padding: 1rem 1.5rem; display: flex; justify-content: space-between; align-items: center; }
        .logo { font-size: 1.4rem; font-weight: 800; color: var(--primary); text-decoration: none; display: flex; align-items: center; gap: 8px; }
        .nav-links { display: flex; gap: 1rem; align-items: center; }
        .btn { padding: 0.5rem 1rem; border-radius: 6px; font-weight: 600; text-decoration: none; font-size: 0.95rem; cursor: pointer; }
        .btn-primary { background: var(--primary); color: white; border: none; }
        .btn-outline { border: 1px solid #cbd5e1; color: var(--text-main); background: white; }
        .btn-accent { background: var(--accent); color: white; border: none; }
        
        .hero { background: linear-gradient(135deg, rgba(27,94,32,0.9), rgba(46,125,50,0.8)), url('images/hero-bg.jpg') center/cover; color: white; padding: 4rem 1.5rem; text-align: center; }
        .hero h1 { font-size: 2.5rem; font-weight: 800; margin-bottom: 1rem; }
        .hero p { font-size: 1.15rem; max-width: 700px; margin: 0 auto 2rem auto; opacity: 0.95; }
        
        .container { max-width: 1200px; margin: 2rem auto; padding: 0 1.5rem; }
        
        /* Alert Banners */
        .alert { padding: 1rem 1.25rem; border-radius: 8px; margin-bottom: 1.5rem; font-weight: 500; display: flex; align-items: center; gap: 10px; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }

        /* Grid */
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.5rem; }
        .card { background: white; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.02); transition: transform 0.2s; }
        .card:hover { transform: translateY(-3px); box-shadow: 0 6px 12px rgba(0,0,0,0.05); }
        .card-body { padding: 1.25rem; }
        .price { font-size: 1.2rem; font-weight: 700; color: var(--primary); margin: 0.5rem 0; }
        .badge { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 50px; font-size: 0.75rem; font-weight: 700; background: #e0f2fe; color: #0369a1; }
    </style>
</head>
<body>

    <header>
        <div class="nav-container">
            <a href="index.php" class="logo">DanFam Market</a>
            <nav class="nav-links">
                <?php if ($isLoggedIn): ?>
                    <span style="font-size: 0.9rem; color: var(--text-muted);">Hello, <strong><?= htmlspecialchars($userName) ?></strong></span>
                    <?php if ($userRole === 'Administrator'): ?>
                        <a href="admin_dashboard.php" class="btn btn-outline">Admin Dashboard</a>
                    <?php elseif ($userRole === 'Farmer'): ?>
                        <a href="farmer_dashboard.php" class="btn btn-outline">Farmer Portal</a>
                        <a href="form.php" class="btn btn-accent">+ Post Harvest</a>
                    <?php elseif ($userRole === 'Buyer'): ?>
                        <a href="cart.php" class="btn btn-outline">🛒 View Cart</a>
                        <a href="orders.php" class="btn btn-outline">My Orders</a>
                    <?php endif; ?>
                    <a href="logout.php" class="btn btn-outline" style="color: #991b1b; border-color: #fca5a5;">Logout</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-outline">Login</a>
                    <a href="register.php" class="btn btn-primary">Register</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <section class="hero">
        <h1>Direct Market Access for Kenyan Farmers & Fishers</h1>
        <p>Connecting smallholder agricultural producers, fishers, and bulk buyers across Kenya transparently and securely.</p>
        <?php if (!$isLoggedIn): ?>
            <div style="display: flex; gap: 1rem; justify-content: center;">
                <a href="register.php" class="btn btn-accent" style="padding: 0.75rem 1.5rem; font-size: 1rem;">Get Started Today</a>
                <a href="login.php" class="btn btn-outline" style="background: rgba(255,255,255,0.1); color: white; border-color: rgba(255,255,255,0.4); padding: 0.75rem 1.5rem; font-size: 1rem;">Sign In</a>
            </div>
        <?php endif; ?>
    </section>

    <div class="container">

        <!-- ERROR / SUCCESS NOTIFICATION HANDLER -->
        <?php if ($currentError && isset($errorMessages[$currentError])): ?>
            <div class="alert alert-error">
                <span>⚠️</span> <span><?= htmlspecialchars($errorMessages[$currentError]) ?></span>
            </div>
        <?php elseif ($currentError): ?>
            <div class="alert alert-error">
                <span>⚠️</span> <span>An error occurred (Code: <?= htmlspecialchars($currentError) ?>). Please try again.</span>
            </div>
        <?php endif; ?>

        <?php if ($currentSuccess && isset($successMessages[$currentSuccess])): ?>
            <div class="alert alert-success">
                <span>✅</span> <span><?= htmlspecialchars($successMessages[$currentSuccess]) ?></span>
            </div>
        <?php endif; ?>

        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h2>Fresh Marketplace Listings</h2>
            <span style="color: var(--text-muted); font-size: 0.9rem;">Verified produce ready for purchase</span>
        </div>

        <?php if (empty($listings)): ?>
            <div style="text-align: center; padding: 4rem 2rem; background: white; border-radius: 10px; border: 1px solid #e2e8f0;">
                <p style="color: var(--text-muted); font-size: 1.1rem; margin-bottom: 1rem;">No approved produce listings are currently available.</p>
                <?php if ($userRole === 'Farmer' || $userRole === 'Administrator'): ?>
                    <a href="form.php" class="btn btn-primary">Post the First Harvest</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="grid">
                <?php foreach ($listings as $item): ?>
                    <div class="card">
                        <div class="card-body">
                            <span class="badge"><?= htmlspecialchars($item['category_name']) ?></span>
                            <h3 style="margin: 0.5rem 0 0.25rem 0; font-size: 1.15rem;"><?= htmlspecialchars($item['name']) ?></h3>
                            <p style="color: var(--text-muted); font-size: 0.85rem; margin: 0 0 0.75rem 0;">📍 <?= htmlspecialchars($item['location']) ?> | By <?= htmlspecialchars($item['farmer_name']) ?></p>
                            <div class="price">KSh <?= number_format((float)$item['price'], 2) ?> <span style="font-size: 0.8rem; font-weight: 400; color: var(--text-muted);">/ <?= htmlspecialchars($item['unit']) ?></span></div>
                            <p style="font-size: 0.9rem; color: #334155; margin-bottom: 1rem; line-height: 1.4;"><?= htmlspecialchars(mb_substr($item['description'] ?? '', 0, 80)) ?><?= mb_strlen($item['description'] ?? '') > 80 ? '...' : '' ?></p>
                            
                            <?php if ($userRole === 'Buyer' || $userRole === 'Administrator' || !$isLoggedIn): ?>
                                <a href="cart.php?add=<?= (int)$item['id'] ?>" class="btn btn-primary" style="display: block; text-align: center; padding: 0.6rem; text-decoration: none;">Add to Cart</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <footer style="background: white; border-top: 1px solid #e2e8f0; margin-top: 4rem; padding: 2rem 0; text-align: center; color: var(--text-muted); font-size: 0.9rem;">
        <p>&copy; <?= date('Y') ?> DanFame Market. Empowering smallholder agricultural communities across Migori and beyond.</p>
    </footer>

</body>
</html>