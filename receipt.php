<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once 'config.php';
require_once 'functions.php';

// Authorization Guard: Require user login
if (function_exists('require_login')) {
    require_login();
} else {
    if (empty($_SESSION['user_id'])) {
        header('Location: login.php?error=unauthorized');
        exit();
    }
}

$order_id = isset($_GET['order_id']) ? filter_var($_GET['order_id'], FILTER_VALIDATE_INT) : 0;
$user_id  = (int) $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? '';

if ($order_id <= 0) {
    header('Location: orders.php?error=invalid_order');
    exit();
}

// Fetch Master Order Details with Buyer Info based on Role
try {
    if ($user_role === 'Administrator') {
        $stmt = $pdo->prepare("
            SELECT o.*, u.name AS buyer_name, u.email AS buyer_email 
            FROM orders o 
            JOIN users u ON o.buyer_id = u.id 
            WHERE o.id = ? LIMIT 1
        ");
        $stmt->execute([$order_id]);
    } elseif ($user_role === 'Farmer') {
        $stmt = $pdo->prepare("
            SELECT DISTINCT o.*, u.name AS buyer_name, u.email AS buyer_email 
            FROM orders o 
            JOIN order_items oi ON o.id = oi.order_id 
            JOIN produce p ON oi.produce_id = p.id 
            JOIN users u ON o.buyer_id = u.id 
            WHERE o.id = ? AND p.farmer_id = ? LIMIT 1
        ");
        $stmt->execute([$order_id, $user_id]);
    } else {
        $stmt = $pdo->prepare("
            SELECT o.*, u.name AS buyer_name, u.email AS buyer_email 
            FROM orders o 
            JOIN users u ON o.buyer_id = u.id 
            WHERE o.id = ? AND o.buyer_id = ? LIMIT 1
        ");
        $stmt->execute([$order_id, $user_id]);
    }

    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        header('Location: orders.php?error=unauthorized_or_not_found');
        exit();
    }

    // 🛑 PAYMENT VERIFICATION GUARD
    // Prevent viewing or printing receipts if the order is still in 'Placed' (unpaid/unconfirmed) status
    if ($order['status'] === 'Placed') {
        header('Location: orders.php?error=payment_required');
        exit();
    }

    // Fetch Itemized Breakdown
    if (function_exists('get_order_items')) {
        $order_items = get_order_items($pdo, $order_id);
    } else {
        $itemsStmt = $pdo->prepare("
            SELECT oi.*, p.name AS produce_name, p.unit 
            FROM order_items oi
            JOIN produce p ON oi.produce_id = p.id
            WHERE oi.order_id = ?
        ");
        $itemsStmt->execute([$order_id]);
        $order_items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {
    error_log('[DanFam receipt.php] DB Error: ' . $e->getMessage());
    header('Location: orders.php?error=database_error');
    exit();
}

$page_title = "Order Receipt #AGRI-" . str_pad((string)$order['id'], 5, '0', STR_PAD_LEFT);

// Generate Verification QR URL (Encodes order verification info)
$verificationUrl = "https://danfammarket.co.ke/verify.php?order_id=" . $order['id'] . "&amount=" . $order['total_amount'];
$qrCodeApiUrl = "https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=" . urlencode($verificationUrl);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> | DanFam Market</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            background-color: var(--gray-light, #f8fafc);
            color: var(--dark, #0f172a);
            font-family: system-ui, -apple-system, sans-serif;
        }
        .receipt-card {
            max-width: 800px;
            margin: 2rem auto;
            background: #ffffff;
            border-radius: var(--radius, 8px);
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            padding: 2.5rem;
            border: 1px solid var(--gray-border, #e2e8f0);
        }
        .receipt-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid var(--primary, #1b5e20);
            padding-bottom: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .receipt-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.5rem;
            background: #f8fafc;
            padding: 1.25rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            border: 1px solid #e2e8f0;
        }
        .print-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        @media print {
            header, footer, .print-actions, .btn-no-print {
                display: none !important;
            }
            body { background: white; }
            .receipt-card { box-shadow: none; border: none; padding: 0; margin: 0; width: 100%; max-width: 100%; }
        }
    </style>
</head>
<body>

    <div class="container" style="max-width: 800px; margin: 0 auto; padding: 1.5rem;">
        
        <!-- Print Action Buttons -->
        <div class="print-actions">
            <a href="orders.php" class="btn btn-outline" style="padding: 0.5rem 1rem; font-size: 0.9rem; text-decoration: none; color: #0f172a; border: 1px solid #cbd5e1; border-radius: 6px;">
                &larr; Back to Orders
            </a>
            <button onclick="window.print()" class="btn btn-accent" style="padding: 0.5rem 1.5rem; font-size: 0.9rem; background: #1b5e20; color: white; border: none; border-radius: 6px; font-weight: 700; cursor: pointer;">
                🖨️ Print Receipt / Download PDF
            </button>
        </div>

        <!-- Receipt Card Container -->
        <div class="receipt-card">
            
            <!-- Header Section with Logo -->
            <div class="receipt-header">
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <!-- Business Logo -->
                    <img src="./img/logo1.png" alt="DanFam Market" style="height: 65px; width: auto; object-fit: contain; border-radius: 6px;" onerror="this.style.display='none'">
                    <div>
                        <h2 style="color: #1b5e20; font-weight: 800; font-size: 1.8rem; margin: 0 0 0.2rem 0;">
                            DanFam Market
                        </h2>
                        <p style="color: #64748b; font-size: 0.88rem; margin: 0; line-height: 1.4;">
                            Direct Farm-to-Buyer Marketplace<br>
                            Migori, Kenya<br>
                            Email: support@DanFamMarket.co.ke | Tel: +254 700 123 456
                        </p>
                    </div>
                </div>
                <div style="text-align: right;">
                    <span style="background: #e8f5e9; color: #1b5e20; font-size: 0.85rem; padding: 0.4rem 0.8rem; border-radius: 4px; font-weight: 700; display: inline-block;">
                        OFFICIAL INVOICE
                    </span>
                    <h3 style="margin: 0.6rem 0 0 0; color: #0f172a; font-size: 1.1rem;">
                        #AGRI-<?= str_pad((string)$order['id'], 5, '0', STR_PAD_LEFT) ?>
                    </h3>
                </div>
            </div>

            <!-- Meta Information Box -->
            <div class="receipt-meta">
                <div>
                    <span style="font-size: 0.75rem; text-transform: uppercase; color: #64748b; font-weight: 700; display: block; margin-bottom: 0.3rem;">Billed To</span>
                    <strong style="color: #0f172a;"><?= htmlspecialchars($order['buyer_name']) ?></strong><br>
                    <span style="font-size: 0.88rem; color: #64748b;"><?= htmlspecialchars($order['buyer_email']) ?></span>
                </div>

                <div>
                    <span style="font-size: 0.75rem; text-transform: uppercase; color: #64748b; font-weight: 700; display: block; margin-bottom: 0.3rem;">Delivery Address</span>
                    <span style="font-size: 0.88rem; color: #334155; line-height: 1.4; display: block;">
                        <?= nl2br(htmlspecialchars($order['shipping_address'])) ?>
                    </span>
                </div>

                <div>
                    <span style="font-size: 0.75rem; text-transform: uppercase; color: #64748b; font-weight: 700; display: block; margin-bottom: 0.3rem;">Order Details</span>
                    <span style="font-size: 0.85rem; color: #334155;"><strong>Date:</strong> <?= date('d M Y, h:i A', strtotime($order['created_at'])) ?></span><br>
                    <span style="font-size: 0.85rem; color: #334155;"><strong>Status:</strong> 
                        <?php if (function_exists('render_status_badge')): ?>
                            <?= render_status_badge($order['status']) ?>
                        <?php else: ?>
                            <strong><?= htmlspecialchars($order['status']) ?></strong>
                        <?php endif; ?>
                    </span>
                </div>
            </div>

            <!-- Itemized Table with Tax Column -->
            <div style="margin-bottom: 2rem;">
                <h4 style="color: #0f172a; margin-bottom: 0.8rem; font-size: 1.1rem;">Order Items</h4>
                <div class="table-responsive">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="border-bottom: 2px solid #e2e8f0; text-align: left; background: #f8fafc;">
                                <th style="padding: 0.75rem; color: #334155; font-size: 0.88rem;">Item Name</th>
                                <th style="padding: 0.75rem; text-align: right; color: #334155; font-size: 0.88rem;">Unit Price</th>
                                <th style="padding: 0.75rem; text-align: center; color: #334155; font-size: 0.88rem;">Quantity</th>
                                <th style="padding: 0.75rem; text-align: right; color: #334155; font-size: 0.88rem;">Tax</th>
                                <th style="padding: 0.75rem; text-align: right; color: #334155; font-size: 0.88rem;">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($order_items as $item): ?>
                                <tr style="border-bottom: 1px solid #e2e8f0;">
                                    <td style="padding: 0.75rem; color: #0f172a;">
                                        <strong><?= htmlspecialchars($item['produce_name']) ?></strong>
                                    </td>
                                    <td style="padding: 0.75rem; text-align: right; color: #334155;">
                                        <?= function_exists('format_currency') ? format_currency($item['unit_price']) : 'KSh ' . number_format((float)$item['unit_price'], 2) ?>
                                    </td>
                                    <td style="padding: 0.75rem; text-align: center; color: #334155;">
                                        <?= (float)$item['quantity'] ?> <?= htmlspecialchars($item['unit']) ?>
                                    </td>
                                    <td style="padding: 0.75rem; text-align: right; color: #64748b; font-weight: 500;">
                                        0.00 KSh
                                    </td>
                                    <td style="padding: 0.75rem; text-align: right; font-weight: 600; color: #1b5e20;">
                                        <?= function_exists('format_currency') ? format_currency($item['subtotal']) : 'KSh ' . number_format((float)$item['subtotal'], 2) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Total Amount Summary & QR Code Verification Section -->
            <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-top: 1.5rem; border-top: 2px solid #e2e8f0; padding-top: 1.25rem;">
                
                <!-- Official Verification QR Code -->
                <div style="display: flex; align-items: center; gap: 1rem; background: #f8fafc; padding: 0.75rem; border-radius: 8px; border: 1px solid #e2e8f0;">
                    <img src="<?= $qrCodeApiUrl ?>" alt="Official Verification QR Code" style="width: 85px; height: 85px; object-fit: contain;">
                    <div>
                        <strong style="color: #1b5e20; font-size: 0.85rem; display: block; margin-bottom: 0.1rem;">Official DanFam Stamp</strong>
                        <span style="color: #64748b; font-size: 0.75rem; line-height: 1.3; display: block;">Scan to verify authenticity & payment status on DanFam Market.</span>
                    </div>
                </div>

                <!-- Total Payable Amount Breakdown -->
                <div style="width: 280px; text-align: right;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.3rem; font-size: 0.9rem; color: #64748b;">
                        <span>Subtotal:</span>
                        <span><?= function_exists('format_currency') ? format_currency($order['total_amount']) : 'KSh ' . number_format((float)$order['total_amount'], 2) ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem; font-size: 0.9rem; color: #64748b;">
                        <span>Tax (VAT 0%):</span>
                        <span>0.00 KSh</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; border-top: 1px solid #cbd5e1; padding-top: 0.5rem; margin-top: 0.3rem; font-size: 1rem; align-items: center;">
                        <span style="color: #0f172a; font-weight: 700;">Total Paid:</span>
                        <strong style="color: #1b5e20; font-size: 1.4rem;">
                            <?= function_exists('format_currency') ? format_currency($order['total_amount']) : 'KSh ' . number_format((float)$order['total_amount'], 2) ?>
                        </strong>
                    </div>
                    <small style="color: #64748b; font-size: 0.78rem; display: block; margin-top: 0.2rem;">Zero-rated agricultural produce (0 KSh Tax).</small>
                </div>
            </div>

            <!-- Footer Note -->
            <div style="margin-top: 2.5rem; text-align: center; border-top: 1px dashed #e2e8f0; padding-top: 1.5rem; color: #64748b; font-size: 0.85rem;">
                <p style="margin: 0 0 0.3rem 0;">Thank you for supporting smallholder farmers through DanFam Market!</p>
                <p style="margin: 0;">This is an electronically generated official invoice backed by digital QR verification.</p>
            </div>
        </div>

    </div>

</body>
</html>