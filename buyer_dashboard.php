<?php
declare(strict_types=1);

$page_title = "Buyer Dashboard";
require_once 'config.php';
require_once 'functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Authorization Guard: Require Buyer (or Administrator) login
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['Buyer', 'Administrator'], true)) {
    header('Location: login.php?error=unauthorized');
    exit;
}

$buyer_id   = (int) $_SESSION['user_id'];
$buyer_name = $_SESSION['user_name'] ?? 'Valued Buyer';

try {
    // Fetch Buyer Statistics
    $orders_count_stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE buyer_id = ?");
    $orders_count_stmt->execute([$buyer_id]);
    $total_orders = $orders_count_stmt->fetchColumn();

    $spent_stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE buyer_id = ? AND order_status != 'Cancelled'");
    $spent_stmt->execute([$buyer_id]);
    $total_spent = $spent_stmt->fetchColumn();

    // Fetch Buyer's Recent Orders (Aligned with danfam_market schema: order_date)
    $orders_stmt = $pdo->prepare("
        SELECT order_id AS id, total_amount, order_status AS status, 
               shipping_address, order_date AS created_at 
        FROM orders 
        WHERE buyer_id = ? 
        ORDER BY order_id DESC 
        LIMIT 10
    ");
    $orders_stmt->execute([$buyer_id]);
    $my_orders = $orders_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('[DanFam Buyer Dashboard Error] ' . $e->getMessage());
    $total_orders = 0;
    $total_spent = 0.00;
    $my_orders = [];
}

require_once 'header.php';
?>

<div class="container" style="max-width: 1100px; margin-top: 2rem; margin-bottom: 4rem;">

    <!-- Welcome Banner -->
    <div style="background: linear-gradient(135deg, #1b5e20 0%, #2e7d32 100%); color: white; padding: 2rem; border-radius: 12px; margin-bottom: 2rem; box-shadow: 0 4px 6px rgba(0,0,0,0.08); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div>
            <span style="background: rgba(255,255,255,0.2); padding: 0.3rem 0.8rem; border-radius: 50px; font-size: 0.8rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Buyer Portal</span>
            <h1 style="margin: 0.5rem 0 0.3rem 0; font-size: 2rem; font-weight: 800;">Jambo, <?= htmlspecialchars($buyer_name) ?>! 👋</h1>
            <p style="margin: 0; opacity: 0.9; font-size: 0.95rem;">Track your fresh produce orders, check shipment progress across Kenya, and manage your cart.</p>
        </div>
        <div>
            <a href="catalogue.php" class="btn" style="background: #ffffff; color: #1b5e20; font-weight: 700; padding: 0.75rem 1.5rem; border-radius: 8px; text-decoration: none; box-shadow: 0 2px 4px rgba(0,0,0,0.1); display: inline-block;">
                🛒 Explore Marketplace
            </a>
        </div>
    </div>

    <!-- Metrics Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.25rem; margin-bottom: 2.5rem;">
        <div style="background: white; padding: 1.5rem; border-radius: 10px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
            <div style="color: #64748b; font-size: 0.88rem; font-weight: 600; text-transform: uppercase; margin-bottom: 0.5rem;">Total Orders Placed</div>
            <div style="color: #0f172a; font-size: 2.2rem; font-weight: 800;"><?= (int)$total_orders ?></div>
        </div>
        <div style="background: white; padding: 1.5rem; border-radius: 10px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
            <div style="color: #64748b; font-size: 0.88rem; font-weight: 600; text-transform: uppercase; margin-bottom: 0.5rem;">Total Investment</div>
            <div style="color: #1b5e20; font-size: 2.2rem; font-weight: 800;">KSh <?= number_format((float)$total_spent, 2) ?></div>
        </div>
        <div style="background: white; padding: 1.5rem; border-radius: 10px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
            <div style="color: #64748b; font-size: 0.88rem; font-weight: 600; text-transform: uppercase; margin-bottom: 0.5rem;">Account Status</div>
            <div style="color: #065f46; font-size: 1.25rem; font-weight: 700; display: flex; align-items: center; gap: 6px; margin-top: 0.5rem;">
                <span style="width: 10px; height: 10px; background: #10b981; border-radius: 50%;"></span> Active Buyer
            </div>
        </div>
    </div>

    <!-- Order History & Tracking Section -->
    <div class="card card-body" style="background: white; padding: 2rem; border-radius: 10px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h3 style="margin: 0; color: #0f172a; font-size: 1.25rem;">📦 My Produce Orders & Status Tracker</h3>
                <p style="margin: 0.2rem 0 0 0; color: #64748b; font-size: 0.9rem;">Review your order history, delivery addresses, and fulfillment status.</p>
            </div>
            <a href="cart.php" style="color: #1b5e20; font-weight: 600; text-decoration: none; font-size: 0.95rem;">View Active Cart &rarr;</a>
        </div>

        <?php if (empty($my_orders)): ?>
            <div style="text-align: center; padding: 3rem 1rem; background: #f8fafc; border-radius: 8px; border: 2px dashed #e2e8f0;">
                <h4 style="color: #0f172a; margin-bottom: 0.4rem;">No Orders Placed Yet</h4>
                <p style="color: #64748b; font-size: 0.95rem; margin-bottom: 1.5rem;">Your cart is ready for fresh farm produce direct from local farmers.</p>
                <a href="catalogue.php" class="btn" style="background: #1b5e20; color: white; padding: 0.65rem 1.5rem; border-radius: 6px; text-decoration: none; font-weight: 600;">Browse Catalogue</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 2px solid #e2e8f0; text-align: left;">
                            <th style="padding: 0.75rem; color: #475569; font-size: 0.88rem;">Order #</th>
                            <th style="padding: 0.75rem; color: #475569; font-size: 0.88rem;">Date Placed</th>
                            <th style="padding: 0.75rem; color: #475569; font-size: 0.88rem;">Shipping Destination</th>
                            <th style="padding: 0.75rem; color: #475569; font-size: 0.88rem;">Total Amount</th>
                            <th style="padding: 0.75rem; color: #475569; font-size: 0.88rem;">Status</th>
                            <th style="padding: 0.75rem; text-align: center; color: #475569; font-size: 0.88rem;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($my_orders as $ord): ?>
                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                <td style="padding: 0.85rem 0.75rem; font-weight: 700; color: #0f172a;">
                                    #DFM-<?= str_pad((string)$ord['id'], 5, '0', STR_PAD_LEFT) ?>
                                </td>
                                <td style="padding: 0.85rem 0.75rem; color: #475569; font-size: 0.9rem;">
                                    <?= date('d M Y, h:i A', strtotime($ord['created_at'])) ?>
                                </td>
                                <td style="padding: 0.85rem 0.75rem; color: #334155; font-size: 0.9rem; max-width: 220px;">
                                    <?= htmlspecialchars($ord['shipping_address']) ?>
                                </td>
                                <td style="padding: 0.85rem 0.75rem; font-weight: 700; color: #1b5e20;">
                                    KSh <?= number_format((float)$ord['total_amount'], 2) ?>
                                </td>
                                <td style="padding: 0.85rem 0.75rem;">
                                    <span style="padding: 0.25rem 0.65rem; border-radius: 50px; font-size: 0.78rem; font-weight: 700; 
                                        background: <?= in_array($ord['status'], ['Delivered', 'Completed'], true) ? '#d1fae5; color: #065f46;' : ($ord['status'] === 'Cancelled' ? '#fee2e2; color: #991b1b;' : '#fef3c7; color: #92400e;') ?>">
                                        <?= htmlspecialchars($ord['status']) ?>
                                    </span>
                                </td>
                                <td style="padding: 0.85rem 0.75rem; text-align: center;">
                                    <div style="display: flex; gap: 6px; justify-content: center; align-items: center;">
                                        
                                        <!-- Order Status / Details Indicator (Receipt printing restricted to Admin/Farmer) -->
                                        <span style="font-size: 0.78rem; color: #64748b; background: #f1f5f9; padding: 0.35rem 0.65rem; border-radius: 4px; font-weight: 500;">
                                            <?= htmlspecialchars($ord['status']) === 'Placed' ? '⏳ Order Placed' : '✅ Verified' ?>
                                        </span>

                                        <!-- Cancel Order (Only if status is Placed) -->
                                        <?php if ($ord['status'] === 'Placed'): ?>
                                            <form action="process.php" method="POST" style="margin: 0;" onsubmit="return confirm('Are you sure you want to cancel this order?');">
                                                <input type="hidden" name="action" value="cancel_order">
                                                <input type="hidden" name="order_id" value="<?= (int)$ord['id'] ?>">
                                                <button type="submit" style="padding: 0.35rem 0.6rem; font-size: 0.78rem; background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; border-radius: 4px; font-weight: 600; cursor: pointer;">
                                                    Cancel
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

</div>

<?php require_once 'footer.php'; ?>