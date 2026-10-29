<?php
declare(strict_types=1);

$page_title = "Order History & Tracking";

require_once 'config.php';
require_once 'functions.php';

// Authorization Guard: Require logged-in user
if (function_exists('require_login')) {
    require_login();
} else {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (empty($_SESSION['user_id'])) {
        header('Location: login.php?error=unauthorized');
        exit();
    }
}

// Ensure CSRF token exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$user_id = (int) $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? '';

// Fetch orders mapped strictly to DanFam Market database schema
try {
    if ($user_role === 'Administrator') {
        // Admins see all orders across the system
        $stmt = $pdo->query("
            SELECT o.order_id AS id, o.total_amount, o.order_status AS status, 
                   o.shipping_address, o.created_at, 
                   u.full_name AS buyer_name, u.email AS buyer_email 
            FROM orders o 
            JOIN users u ON o.buyer_id = u.user_id 
            ORDER BY o.order_id DESC
        ");
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($user_role === 'Farmer') {
        // Farmers see orders containing their produce items
        $stmt = $pdo->prepare("
            SELECT DISTINCT o.order_id AS id, o.total_amount, o.order_status AS status, 
                   o.shipping_address, o.created_at, 
                   u.full_name AS buyer_name, u.email AS buyer_email 
            FROM orders o 
            JOIN order_items oi ON o.order_id = oi.order_id 
            JOIN produce p ON oi.produce_id = p.produce_id 
            JOIN users u ON o.buyer_id = u.user_id 
            WHERE p.farmer_id = ? 
            ORDER BY o.order_id DESC
        ");
        $stmt->execute([$user_id]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Buyers see only their own placed orders
        $stmt = $pdo->prepare("
            SELECT o.order_id AS id, o.total_amount, o.order_status AS status, 
                   o.shipping_address, o.created_at, 
                   u.full_name AS buyer_name, u.email AS buyer_email 
            FROM orders o 
            JOIN users u ON o.buyer_id = u.user_id 
            WHERE o.buyer_id = ? 
            ORDER BY o.order_id DESC
        ");
        $stmt->execute([$user_id]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log('[DanFam Market orders.php] Query Error: ' . $e->getMessage());
    $orders = [];
}

require_once 'header.php';
?>

<div class="container" style="max-width: 1100px; margin-top: 2rem; margin-bottom: 4rem;">

    <!-- NOTIFICATION BANNERS -->
    <?php if (isset($_GET['success']) && $_GET['success'] === 'order_cancelled'): ?>
        <div style="background: #d1fae5; color: #065f46; padding: 0.85rem 1.25rem; border-radius: 6px; margin-bottom: 1.5rem; font-weight: 600; border: 1px solid #a7f3d0;">
            ✅ Success! The order has been cancelled and produce stock restored to inventory.
        </div>
    <?php elseif (isset($_GET['success']) && $_GET['success'] === 'status_updated'): ?>
        <div style="background: #d1fae5; color: #065f46; padding: 0.85rem 1.25rem; border-radius: 6px; margin-bottom: 1.5rem; font-weight: 600; border: 1px solid #a7f3d0;">
            ✅ Order status updated successfully.
        </div>
    <?php elseif (isset($_GET['error'])): ?>
        <div style="background: #fee2e2; color: #991b1b; padding: 0.85rem 1.25rem; border-radius: 6px; margin-bottom: 1.5rem; font-weight: 600; border: 1px solid #fca5a5;">
            ⚠️ Unable to process request. Please check permissions or try again.
        </div>
    <?php endif; ?>

    <!-- Page Header & Navigation -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; border-bottom: 2px solid var(--gray-border, #e2e8f0); padding-bottom: 1rem;">
        <div>
            <h2 style="color: var(--dark, #0f172a); margin-bottom: 0.2rem;">Order Management & Tracking</h2>
            <p style="color: var(--text-muted, #64748b); font-size: 0.95rem; margin: 0;">
                <?= $user_role === 'Buyer' ? 'Track your active and past produce bookings.' : 'Monitor direct crop orders placed by buyers across Kenya.' ?>
            </p>
        </div>
        <a href="<?= $user_role === 'Farmer' ? 'farmer_dashboard.php' : ($user_role === 'Administrator' ? 'admin_dashboard.php' : 'catalogue.php') ?>" class="btn btn-outline" style="font-size: 0.88rem; padding: 0.5rem 1rem; border: 1px solid #cbd5e1; border-radius: 6px; text-decoration: none; color: #0f172a;">
            &larr; Back to Dashboard
        </a>
    </div>

    <!-- Orders Card -->
    <div class="card card-body" style="padding: 1.5rem; background: white; border-radius: 8px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
        <?php if (empty($orders)): ?>
            <div style="text-align: center; padding: 3rem 1rem;">
                <h3 style="color: var(--dark, #0f172a); margin-bottom: 0.5rem;">📭 No Orders Found</h3>
                <p style="color: var(--text-muted, #64748b); margin-bottom: 1.5rem;">There are no active or historical orders recorded for your account.</p>
                <a href="catalogue.php" class="btn btn-accent" style="background: #1b5e20; color: white; padding: 0.6rem 1.25rem; border-radius: 6px; text-decoration: none; font-weight: 600;">Explore Fresh Produce</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 2px solid #e2e8f0; text-align: left;">
                            <th style="padding: 0.75rem;">Order #</th>
                            <th style="padding: 0.75rem;">Date Placed</th>
                            <?php if ($user_role !== 'Buyer'): ?>
                                <th style="padding: 0.75rem;">Customer / Buyer</th>
                            <?php endif; ?>
                            <th style="padding: 0.75rem;">Shipping Address</th>
                            <th style="padding: 0.75rem;">Total Amount</th>
                            <th style="padding: 0.75rem;">Status</th>
                            <th style="padding: 0.75rem; text-align: center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                            <tr style="border-bottom: 1px solid #e2e8f0;">
                                <td style="padding: 0.85rem 0.75rem;"><strong>#DFM-<?= str_pad((string)$order['id'], 5, '0', STR_PAD_LEFT) ?></strong></td>
                                <td style="padding: 0.85rem 0.75rem; font-size: 0.9rem; color: #475569;"><?= date('d M Y, h:i A', strtotime($order['created_at'])) ?></td>
                                <?php if ($user_role !== 'Buyer'): ?>
                                    <td style="padding: 0.85rem 0.75rem;">
                                        <strong><?= htmlspecialchars($order['buyer_name'] ?? 'Buyer') ?></strong><br>
                                        <small style="color: #64748b;"><?= htmlspecialchars($order['buyer_email'] ?? '') ?></small>
                                    </td>
                                <?php endif; ?>
                                <td style="padding: 0.85rem 0.75rem; font-size: 0.9rem; max-width: 200px;"><?= htmlspecialchars($order['shipping_address']) ?></td>
                                <td style="padding: 0.85rem 0.75rem; font-weight: 700; color: #1b5e20;">
                                    KSh <?= number_format((float)$order['total_amount'], 2) ?>
                                </td>
                                <td style="padding: 0.85rem 0.75rem;">
                                    <span style="padding: 0.25rem 0.65rem; border-radius: 50px; font-size: 0.8rem; font-weight: 700; 
                                        background: <?= in_array($order['status'], ['Delivered', 'Completed'], true) ? '#d1fae5; color: #065f46;' : ($order['status'] === 'Cancelled' ? '#fee2e2; color: #991b1b;' : '#fef3c7; color: #92400e;') ?>">
                                        <?= htmlspecialchars($order['status']) ?>
                                    </span>
                                </td>
                                <td style="padding: 0.85rem 0.75rem; text-align: center;">
                                    <div style="display: flex; gap: 6px; justify-content: center; align-items: center; flex-wrap: wrap;">
                                        
                                        <!-- PRINTABLE RECEIPT LINK (Restricted to successful/paid statuses) -->
                                        <?php if (in_array($order['status'], ['Confirmed', 'Dispatched', 'Delivered', 'Completed'], true)): ?>
                                            <a href="admin_receipt.php?order_id=<?= (int)$order['id'] ?>" class="btn btn-outline" style="padding: 0.35rem 0.65rem; font-size: 0.82rem; border: 1px solid #1b5e20; border-radius: 4px; text-decoration: none; color: #1b5e20; background: #f0fdf4; font-weight: 600;" target="_blank" title="View & Print Payment Receipt">
                                                🖨️ Print Receipt
                                            </a>
                                        <?php else: ?>
                                            <span style="font-size: 0.78rem; color: #94a3b8; font-style: italic; padding: 0.35rem 0.5rem;" title="Receipt available after order payment is confirmed">
                                                ⏳ Pending Payment
                                            </span>
                                        <?php endif; ?>

                                        <!-- STATUS UPDATE DROPDOWN (Admin & Farmer Only) -->
                                        <?php if (($user_role === 'Administrator' || $user_role === 'Farmer') && $order['status'] !== 'Cancelled'): ?>
                                            <form action="process.php" method="POST" style="display: inline-block; margin: 0;">
                                                <input type="hidden" name="action" value="update_order_status">
                                                <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                                <select name="status" onchange="this.form.submit()" style="padding: 0.35rem 0.5rem; font-size: 0.82rem; border-radius: 4px; border: 1px solid #cbd5e1; background: white; cursor: pointer;">
                                                    <option value="Placed" <?= $order['status'] === 'Placed' ? 'selected' : '' ?>>Placed</option>
                                                    <option value="Confirmed" <?= $order['status'] === 'Confirmed' ? 'selected' : '' ?>>Confirmed</option>
                                                    <option value="Dispatched" <?= $order['status'] === 'Dispatched' ? 'selected' : '' ?>>Dispatched</option>
                                                    <option value="Delivered" <?= $order['status'] === 'Delivered' ? 'selected' : '' ?>>Delivered</option>
                                                </select>
                                            </form>
                                        <?php endif; ?>

                                        <!-- ORDER CANCELLATION BUTTON -->
                                        <?php if (
                                            $order['status'] !== 'Cancelled' && (
                                                $user_role === 'Administrator' || 
                                                $user_role === 'Farmer' || 
                                                ($user_role === 'Buyer' && $order['status'] === 'Placed')
                                            )
                                        ): ?>
                                            <form action="process.php" method="POST" style="display: inline-block; margin: 0;" onsubmit="return confirm('Are you sure you want to cancel this order? Item quantities will be returned to inventory.');">
                                                <input type="hidden" name="action" value="cancel_order">
                                                <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                                <button type="submit" class="btn" style="padding: 0.35rem 0.65rem; font-size: 0.82rem; background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; border-radius: 4px; font-weight: 600; cursor: pointer;">
                                                    🗑️ Cancel
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