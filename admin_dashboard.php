<?php
session_start();
require_once 'config.php';

// Role Guard: Require Administrator Access (Must run BEFORE any output/header.php)
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Administrator') {
    header('Location: login.php');
    exit;
}

// Now it is safe to include the header since checks passed and no output has occurred
include 'header.php';

// Fetch System Summary Metrics
$total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_farmers = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'Farmer'")->fetchColumn();
$total_buyers = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'Buyer'")->fetchColumn();
$total_orders = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$pending_listings = $pdo->query("SELECT COUNT(*) FROM produce WHERE status = 'Pending'")->fetchColumn();

// Fetch All Produce Listings for Moderation
$produce_stmt = $pdo->query("SELECT p.*, c.name AS category_name, u.name AS farmer_name 
                           FROM produce p 
                           JOIN categories c ON p.category_id = c.id 
                           JOIN users u ON p.farmer_id = u.id 
                           ORDER BY p.id DESC");
$all_produce = $produce_stmt->fetchAll();

// Fetch All System Orders & Items Overview
$orders_stmt = $pdo->query("SELECT o.*, u.name AS buyer_name, u.email AS buyer_email 
                            FROM orders o 
                            JOIN users u ON o.buyer_id = u.id 
                            ORDER BY o.id DESC");
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

        <!-- Global Header Actions & Logout -->
        <nav style="display: flex; align-items: center; gap: 1.25rem;">
            <a href="index.php" style="color: #334155; font-weight: 600; text-decoration: none; font-size: 0.95rem;">Live Marketplace</a>
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
            <a href="index.php" style="padding: 0.45rem 0.9rem; border-radius: 6px; color: #475569; font-weight: 500; font-size: 0.88rem; text-decoration: none; transition: background 0.2s;" onmouseover="this.style.background='#e2e8f0'" onmouseout="this.style.background='transparent'">
                Live Marketplace
            </a>
            <a href="orders.php" style="padding: 0.45rem 0.9rem; border-radius: 6px; color: #475569; font-weight: 500; font-size: 0.88rem; text-decoration: none; transition: background 0.2s;" onmouseover="this.style.background='#e2e8f0'" onmouseout="this.style.background='transparent'">
                System Orders
            </a>
        </nav>

        <!-- Right Side: Admin Badge & User Status -->
        <div style="display: flex; align-items: center; gap: 0.85rem;">
            <!-- Admin Role Pill -->
            <div style="display: inline-flex; align-items: center; gap: 8px; background: #ffffff; border: 1px solid #fecaca; padding: 0.3rem 0.8rem; border-radius: 50px; font-size: 0.82rem; color: #991b1b;">
                <span style="width: 8px; height: 8px; border-radius: 50%; background: #ef4444;"></span>
                <span><strong><?= htmlspecialchars($_SESSION['user_name'] ?? 'System Admin') ?></strong> (Administrator)</span>
            </div>
        </div>

    </div>
</section>

    <div class="container">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; margin-bottom: 1.5rem;">
            <div>
                <h2>System Control Panel</h2>
                <p style="color: var(--text-muted);">Manage farmer accounts, review produce submissions, and update order lifecycles.</p>
            </div>
            <?php if ($pending_listings > 0): ?>
                <span class="badge badge-pending" style="font-size: 0.9rem; padding: 0.5rem 1rem;">
                    ⚠️ <?= $pending_listings ?> Produce Listing(s) Pending Approval
                </span>
            <?php endif; ?>
        </div>

        <?php if (isset($_GET['msg'])): ?>
            <div style="background: var(--primary-soft); color: var(--primary); padding: 0.8rem 1.2rem; border-radius: var(--radius); margin-bottom: 1.5rem; font-weight: 600;">
                <?php
                    if ($_GET['msg'] === 'status_updated') echo "✅ Produce listing status successfully updated.";
                    elseif ($_GET['msg'] === 'order_updated') echo "✅ Order lifecycle state successfully changed.";
                    else echo "✅ Action completed successfully.";
                ?>
            </div>
        <?php endif; ?>

        <!-- Key Metrics Cards -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 2.5rem;">
            <div class="card card-body" style="text-align: center;">
                <h3 style="color: var(--primary); font-size: 2rem;"><?= $total_users ?></h3>
                <p style="color: var(--text-muted); font-size: 0.9rem; font-weight: 600;">Total Users</p>
            </div>
            <div class="card card-body" style="text-align: center;">
                <h3 style="color: var(--primary); font-size: 2rem;"><?= $total_farmers ?></h3>
                <p style="color: var(--text-muted); font-size: 0.9rem; font-weight: 600;">Farmers</p>
            </div>
            <div class="card card-body" style="text-align: center;">
                <h3 style="color: var(--primary); font-size: 2rem;"><?= $total_buyers ?></h3>
                <p style="color: var(--text-muted); font-size: 0.9rem; font-weight: 600;">Buyers</p>
            </div>
            <div class="card card-body" style="text-align: center;">
                <h3 style="color: var(--accent); font-size: 2rem;"><?= $total_orders ?></h3>
                <p style="color: var(--text-muted); font-size: 0.9rem; font-weight: 600;">Orders Placed</p>
            </div>
        </div>

        <!-- Section 1: Produce Approval & Listing Moderation -->
        <div class="card card-body" style="margin-bottom: 2.5rem;">
            <h3>Moderate Farmer Produce Listings</h3>
            <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1rem;">Only produce marked as <strong>Approved</strong> will be visible in the public marketplace catalogue.</p>
            
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Produce</th>
                            <th>Farmer</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Quantity</th>
                            <th>Location</th>
                            <th>Status</th>
                            <th style="text-align: center;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($all_produce)): ?>
                            <tr><td colspan="8" style="text-align:center;">No produce listings recorded yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($all_produce as $p): ?>
                                <tr>
                                    <td>
                                        <strong style="color: var(--dark);"><?= htmlspecialchars($p['name']) ?></strong>
                                    </td>
                                    <td><?= htmlspecialchars($p['farmer_name']) ?></td>
                                    <td><span class="badge badge-confirmed"><?= htmlspecialchars($p['category_name']) ?></span></td>
                                    <td><strong>KSh <?= number_format($p['price'], 2) ?></strong></td>
                                    <td><?= $p['quantity'] ?> <?= htmlspecialchars($p['unit']) ?></td>
                                    <td>📍 <?= htmlspecialchars($p['location']) ?></td>
                                    <td>
                                        <span class="badge badge-<?= strtolower($p['status']) ?>">
                                            <?= $p['status'] ?>
                                        </span>
                                    </td>
                                    <td style="text-align: center;">
                                        <div style="display: inline-flex; gap: 6px;">
                                            <!-- APPROVE FORM -->
                                            <?php if ($p['status'] !== 'Approved'): ?>
                                                <form action="process.php" method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="approve_produce">
                                                    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                                                    <button type="submit" class="btn" style="padding: 0.3rem 0.7rem; font-size: 0.8rem; background: var(--primary, #1b5e20); color: white; border: none; border-radius: 4px; cursor: pointer;">Approve</button>
                                                </form>
                                            <?php endif; ?>

                                            <!-- REJECT / DELETE FORM -->
                                            <?php if ($p['status'] !== 'Rejected'): ?>
                                                <form action="process.php" method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to reject this produce listing?');">
                                                    <input type="hidden" name="action" value="reject_produce">
                                                    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                                                    <button type="submit" class="btn" style="padding: 0.3rem 0.7rem; font-size: 0.8rem; background: #d32f2f; color: white; border: none; border-radius: 4px; cursor: pointer;">Reject</button>
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
        <div class="card card-body">
            <h3>🚚 Order Lifecycle Tracker</h3>
            <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1rem;">Update order states through: <code>Placed</code> &rarr; <code>Confirmed</code> &rarr; <code>Dispatched</code> &rarr; <code>Delivered</code>.</p>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Buyer Details</th>
                            <th>Delivery Address</th>
                            <th>Total Amount</th>
                            <th>Date</th>
                            <th>Current Status</th>
                            <th>Update Lifecycle</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($all_orders)): ?>
                            <tr><td colspan="7" style="text-align:center;">No orders have been submitted yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($all_orders as $ord): ?>
                                <tr>
                                    <td><strong>#<?= $ord['id'] ?></strong></td>
                                    <td>
                                        <?= htmlspecialchars($ord['buyer_name']) ?><br>
                                        <small style="color: var(--text-muted);"><?= htmlspecialchars($ord['buyer_email']) ?></small>
                                    </td>
                                    <td style="max-width: 200px;"><?= htmlspecialchars($ord['shipping_address']) ?></td>
                                    <td><strong style="color: var(--primary);">KSh <?= number_format($ord['total_amount'], 2) ?></strong></td>
                                    <td><small><?= date('Y-m-d H:i', strtotime($ord['created_at'])) ?></small></td>
                                    <td>
                                        <span class="badge badge-<?= strtolower($ord['status']) ?>">
                                            <?= $ord['status'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <form action="process.php" method="POST" style="display: flex; gap: 6px; align-items: center;">
                                            <input type="hidden" name="action" value="update_order_status">
                                            <input type="hidden" name="order_id" value="<?= $ord['id'] ?>">
                                            <select name="status" style="padding: 0.35rem; border-radius: var(--radius); border: 1px solid var(--gray-border); font-size: 0.85rem;">
                                                <option value="Placed" <?= $ord['status'] === 'Placed' ? 'selected' : '' ?>>Placed</option>
                                                <option value="Confirmed" <?= $ord['status'] === 'Confirmed' ? 'selected' : '' ?>>Confirmed</option>
                                                <option value="Dispatched" <?= $ord['status'] === 'Dispatched' ? 'selected' : '' ?>>Dispatched</option>
                                                <option value="Delivered" <?= $ord['status'] === 'Delivered' ? 'selected' : '' ?>>Delivered</option>
                                            </select>
                                            <button type="submit" class="btn btn-outline" style="padding: 0.35rem 0.75rem; font-size: 0.8rem;">Save</button>
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

    <footer>
        <p>&copy; 2026 DanFam Market. System Administrator Portal.</p>
    </footer>
</body>
</html>