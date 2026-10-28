<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';

// Role Guard: Require Administrator Access (Must run BEFORE any output/header.php)
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'Administrator') {
    header('Location: login.php');
    exit;
}

// Now it is safe to include the header since checks passed and no output has occurred
include 'header.php';

// Fetch System Summary Metrics
$total_users    = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_farmers  = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'Farmer'")->fetchColumn();
$total_buyers   = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'Buyer'")->fetchColumn();
$total_orders   = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$pending_listings = $pdo->query("SELECT COUNT(*) FROM produce WHERE status = 'Pending'")->fetchColumn();

// Fetch All Produce Listings for Moderation (Mapped to danfam_market schema columns)
$produce_stmt = $pdo->query("
    SELECT p.produce_id AS id, p.produce_name AS name, p.price_per_unit AS price, 
           p.quantity_available AS quantity, p.unit, p.location, p.status, 
           c.category_name, u.full_name AS farmer_name 
    FROM produce p 
    JOIN categories c ON p.category_id = c.category_id 
    JOIN users u ON p.farmer_id = u.user_id 
    ORDER BY p.produce_id DESC
");
$all_produce = $produce_stmt->fetchAll();

// Fetch All System Orders & Items Overview (Mapped to danfam_market schema columns)
$orders_stmt = $pdo->query("
    SELECT o.order_id AS id, o.total_amount, o.order_status AS status, o.shipping_address, o.order_date AS created_at, 
           u.full_name AS buyer_name, u.email AS buyer_email 
    FROM orders o 
    JOIN users u ON o.buyer_id = u.user_id 
    ORDER BY o.order_id DESC
");
$all_orders = $orders_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - DanFam Market</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<!-- PRIMARY ADMIN NAVIGATION HEADER -->
<header style="background: #ffffff; border-bottom: 1px solid #e2e8f0; position: sticky; top: 0; z-index: 1000; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
    <div style="max-width: 1200px; margin: 0 auto; padding: 0.85rem 1.5rem; display: flex; justify-content: space-between; align-items: center;">
        
        <!-- Brand Logo & Admin Badge -->
        <a href="index.php" style="font-size: 1.35rem; font-weight: 800; color: #1b5e20; text-decoration: none; display: flex; align-items: center; gap: 8px;">
             DanFam Market 
            <span style="font-size: 0.75rem; background: #0f172a; color: #ffffff; padding: 3px 8px; border-radius: 4px; font-weight: 600;">Admin Console</span>
        </a>

        <!-- Global Header Actions -->
        <nav style="display: flex; align-items: center; gap: 1.25rem;">
            <a href="catalogue.php" style="color: #334155; font-weight: 600; text-decoration: none; font-size: 0.95rem;">Live Marketplace</a>
        </nav>

    </div>
</header>

<!-- ADMIN SUB-HEADER NAVIGATION BANNER -->
<section class="sub-header-banner" style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; padding: 0.65rem 0;">
    <div class="sub-header-wrapper" style="max-width: 1200px; margin: 0 auto; padding: 0 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        
        <!-- Left Side: System Management Navigation Tabs -->
        <nav style="display: flex; gap: 0.5rem; align-items: center;">
            <a href="admin_dashboard.php" style="padding: 0.45rem 0.9rem; border-radius: 6px; background: #0f172a; color: #ffffff; font-weight: 600; font-size: 0.88rem; text-decoration: none; box-shadow: 0 1px 2px rgba(0,0,0,0.1);">
                Control Center
            </a>
            <a href="catalogue.php" style="padding: 0.45rem 0.9rem; border-radius: 6px; color: #475569; font-weight: 500; font-size: 0.88rem; text-decoration: none;">
                Live Marketplace
            </a>
        </nav>

        <!-- Right Side: Admin Badge & User Status -->
        <div style="display: flex; align-items: center; gap: 0.85rem;">
            <div style="display: inline-flex; align-items: center; gap: 8px; background: #ffffff; border: 1px solid #fecaca; padding: 0.3rem 0.8rem; border-radius: 50px; font-size: 0.82rem; color: #991b1b;">
                <span style="width: 8px; height: 8px; border-radius: 50%; background: #ef4444;"></span>
                <span><strong><?= htmlspecialchars($_SESSION['user_name'] ?? 'System Admin') ?></strong> (Administrator)</span>
            </div>
        </div>

    </div>
</section>

    <div class="container" style="max-width: 1200px; margin: 2rem auto; padding: 0 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; margin-bottom: 1.5rem;">
            <div>
                <h2>System Control Panel</h2>
                <p style="color: #64748b;">Manage farmer accounts, review produce submissions, and update order lifecycles.</p>
            </div>
            <?php if ($pending_listings > 0): ?>
                <span class="badge badge-pending" style="font-size: 0.9rem; padding: 0.5rem 1rem; background: #fff3cd; color: #856404; border-radius: 6px;">
                    ⚠️ <?= $pending_listings ?> Produce Listing(s) Pending Approval
                </span>
            <?php endif; ?>
        </div>

        <?php if (isset($_GET['msg'])): ?>
            <div style="background: #d4edda; color: #155724; padding: 0.8rem 1.2rem; border-radius: 6px; margin-bottom: 1.5rem; font-weight: 600;">
                <?php
                    if ($_GET['msg'] === 'status_updated') echo "✅ Produce listing status successfully updated.";
                    elseif ($_GET['msg'] === 'order_updated') echo "✅ Order lifecycle state successfully changed.";
                    else echo "✅ Action completed successfully.";
                ?>
            </div>
        <?php endif; ?>

        <!-- Key Metrics Cards -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 2.5rem;">
            <div class="card card-body" style="background: white; padding: 1.5rem; border-radius: 8px; border: 1px solid #e2e8f0; text-align: center;">
                <h3 style="color: #1b5e20; font-size: 2rem; margin: 0;"><?= $total_users ?></h3>
                <p style="color: #64748b; font-size: 0.9rem; font-weight: 600; margin-top: 0.5rem;">Total Users</p>
            </div>
            <div class="card card-body" style="background: white; padding: 1.5rem; border-radius: 8px; border: 1px solid #e2e8f0; text-align: center;">
                <h3 style="color: #1b5e20; font-size: 2rem; margin: 0;"><?= $total_farmers ?></h3>
                <p style="color: #64748b; font-size: 0.9rem; font-weight: 600; margin-top: 0.5rem;">Farmers</p>
            </div>
            <div class="card card-body" style="background: white; padding: 1.5rem; border-radius: 8px; border: 1px solid #e2e8f0; text-align: center;">
                <h3 style="color: #1b5e20; font-size: 2rem; margin: 0;"><?= $total_buyers ?></h3>
                <p style="color: #64748b; font-size: 0.9rem; font-weight: 600; margin-top: 0.5rem;">Buyers</p>
            </div>
            <div class="card card-body" style="background: white; padding: 1.5rem; border-radius: 8px; border: 1px solid #e2e8f0; text-align: center;">
                <h3 style="color: #ff9800; font-size: 2rem; margin: 0;"><?= $total_orders ?></h3>
                <p style="color: #64748b; font-size: 0.9rem; font-weight: 600; margin-top: 0.5rem;">Orders Placed</p>
            </div>
        </div>

        <!-- Section 1: Produce Approval & Listing Moderation -->
        <div class="card card-body" style="background: white; padding: 1.5rem; border-radius: 8px; border: 1px solid #e2e8f0; margin-bottom: 2.5rem;">
            <h3>Moderate Farmer Produce Listings</h3>
            <p style="color: #64748b; font-size: 0.9rem; margin-bottom: 1rem;">Only produce marked as <strong>Approved</strong> will be visible in the public marketplace catalogue.</p>
            
            <div class="table-responsive">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 2px solid #e2e8f0; text-align: left;">
                            <th style="padding: 0.75rem;">Produce</th>
                            <th style="padding: 0.75rem;">Farmer</th>
                            <th style="padding: 0.75rem;">Category</th>
                            <th style="padding: 0.75rem;">Price</th>
                            <th style="padding: 0.75rem;">Quantity</th>
                            <th style="padding: 0.75rem;">Location</th>
                            <th style="padding: 0.75rem;">Status</th>
                            <th style="padding: 0.75rem; text-align: center;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($all_produce)): ?>
                            <tr><td colspan="8" style="text-align:center; padding: 1.5rem;">No produce listings recorded yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($all_produce as $p): ?>
                                <tr style="border-bottom: 1px solid #e2e8f0;">
                                    <td style="padding: 0.75rem;"><strong style="color: #0f172a;"><?= htmlspecialchars($p['name']) ?></strong></td>
                                    <td style="padding: 0.75rem;"><?= htmlspecialchars($p['farmer_name']) ?></td>
                                    <td style="padding: 0.75rem;"><?= htmlspecialchars($p['category_name']) ?></td>
                                    <td style="padding: 0.75rem;"><strong>KSh <?= number_format((float)$p['price'], 2) ?></strong></td>
                                    <td style="padding: 0.75rem;"><?= $p['quantity'] ?> <?= htmlspecialchars($p['unit']) ?></td>
                                    <td style="padding: 0.75rem;">📍 <?= htmlspecialchars($p['location']) ?></td>
                                    <td style="padding: 0.75rem;"><?= htmlspecialchars($p['status']) ?></td>
                                    <td style="padding: 0.75rem; text-align: center;">
                                        <div style="display: inline-flex; gap: 6px;">
                                            <!-- APPROVE FORM -->
                                            <?php if ($p['status'] !== 'Approved'): ?>
                                                <form action="process.php" method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="approve_produce">
                                                    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                                    <button type="submit" style="padding: 0.3rem 0.7rem; font-size: 0.8rem; background: #1b5e20; color: white; border: none; border-radius: 4px; cursor: pointer;">Approve</button>
                                                </form>
                                            <?php endif; ?>

                                            <!-- REJECT FORM -->
                                            <?php if ($p['status'] !== 'Rejected'): ?>
                                                <form action="process.php" method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to reject this produce listing?');">
                                                    <input type="hidden" name="action" value="reject_produce">
                                                    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                                    <button type="submit" style="padding: 0.3rem 0.7rem; font-size: 0.8rem; background: #d32f2f; color: white; border: none; border-radius: 4px; cursor: pointer;">Reject</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Section 2: Order Lifecycle Management -->
        <div class="card card-body" style="background: white; padding: 1.5rem; border-radius: 8px; border: 1px solid #e2e8f0;">
            <h3>Order Lifecycle Tracker</h3>
            <p style="color: #64748b; font-size: 0.9rem; margin-bottom: 1rem;">Update order states through: <code>Placed</code> &rarr; <code>Confirmed</code> &rarr; <code>Dispatched</code> &rarr; <code>Delivered</code>.</p>

            <div class="table-responsive">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 2px solid #e2e8f0; text-align: left;">
                            <th style="padding: 0.75rem;">Order ID</th>
                            <th style="padding: 0.75rem;">Buyer Details</th>
                            <th style="padding: 0.75rem;">Delivery Address</th>
                            <th style="padding: 0.75rem;">Total Amount</th>
                            <th style="padding: 0.75rem;">Date</th>
                            <th style="padding: 0.75rem;">Current Status</th>
                            <th style="padding: 0.75rem;">Update Lifecycle</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($all_orders)): ?>
                            <tr><td colspan="7" style="text-align:center; padding: 1.5rem;">No orders have been submitted yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($all_orders as $ord): ?>
                                <tr style="border-bottom: 1px solid #e2e8f0;">
                                    <td style="padding: 0.75rem;"><strong>#<?= $ord['id'] ?></strong></td>
                                    <td style="padding: 0.75rem;">
                                        <?= htmlspecialchars($ord['buyer_name']) ?><br>
                                        <small style="color: #64748b;"><?= htmlspecialchars($ord['buyer_email']) ?></small>
                                    </td>
                                    <td style="padding: 0.75rem; max-width: 200px;"><?= htmlspecialchars($ord['shipping_address']) ?></td>
                                    <td style="padding: 0.75rem;"><strong style="color: #1b5e20;">KSh <?= number_format((float)$ord['total_amount'], 2) ?></strong></td>
                                    <td style="padding: 0.75rem;"><small><?= date('Y-m-d H:i', strtotime($ord['created_at'])) ?></small></td>
                                    <td style="padding: 0.75rem;"><?= htmlspecialchars($ord['status']) ?></td>
                                    <td style="padding: 0.75rem;">
                                        <form action="process.php" method="POST" style="display: flex; gap: 6px; align-items: center;">
                                            <input type="hidden" name="action" value="update_order_status">
                                            <input type="hidden" name="order_id" value="<?= $ord['id'] ?>">
                                            <select name="status" style="padding: 0.35rem; border-radius: 4px; border: 1px solid #cbd5e1; font-size: 0.85rem;">
                                                <option value="Placed" <?= $ord['status'] === 'Placed' ? 'selected' : '' ?>>Placed</option>
                                                <option value="Confirmed" <?= $ord['status'] === 'Confirmed' ? 'selected' : '' ?>>Confirmed</option>
                                                <option value="Dispatched" <?= $ord['status'] === 'Dispatched' ? 'selected' : '' ?>>Dispatched</option>
                                                <option value="Delivered" <?= $ord['status'] === 'Delivered' ? 'selected' : '' ?>>Delivered</option>
                                            </select>
                                            <button type="submit" style="padding: 0.35rem 0.75rem; font-size: 0.8rem; background: #1b5e20; color: white; border: none; border-radius: 4px; cursor: pointer;">Save</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <footer style="text-align: center; padding: 2rem; color: #64748b; font-size: 0.9rem;">
        <p>&copy; 2026 DanFam Market. System Administrator Portal.</p>
    </footer>
</body>
</html>