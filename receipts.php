<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'functions.php';

// Authorization Guard: Require user login
require_login();

$order_id = isset($_GET['order_id']) ? (int) $_GET['order_id'] : 0;
$user_id  = (int) ($_SESSION['user_id'] ?? 0);
$user_role = (string) ($_SESSION['user_role'] ?? '');

if ($order_id <= 0) {
    redirect('orders.php');
}

// Fetch Master Order Details with Buyer Info mapped to danfam_market schema
$stmt = $pdo->prepare("
    SELECT o.*, u.full_name AS buyer_name, u.email AS buyer_email 
    FROM orders o 
    JOIN users u ON o.buyer_id = u.user_id 
    WHERE o.order_id = ?
");
$stmt->execute([$order_id]);
$order = $stmt->fetch();

if (!$order) {
    redirect('orders.php');
}

// Ownership Authorization Check: Buyers can only view their own receipts; Farmers/Admins can view all
if ($user_role === 'Buyer' && (int)$order['buyer_id'] !== $user_id) {
    redirect('orders.php');
}

// Fetch Itemized Breakdown
$order_items = get_order_items($pdo, $order_id);

$page_title = "Order Receipt #AGRI-" . str_pad((string)$order_id, 5, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt #AGRI-<?= str_pad((string)$order['order_id'], 5, '0', STR_PAD_LEFT) ?> | DanFam Market</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            background-color: var(--gray-light, #f8fafc);
            color: var(--dark, #0f172a);
            font-family: Arial, sans-serif;
        }
        .receipt-card {
            max-width: 800px;
            margin: 2rem auto;
            background: #ffffff;
            border-radius: var(--radius, 6px);
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
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
            border-radius: var(--radius, 6px);
            margin-bottom: 2rem;
            border: 1px solid var(--gray-border, #e2e8f0);
        }
        .print-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        /* Print-specific style rules */
        @media print {
            header, footer, .print-actions, .btn-no-print {
                display: none !important;
            }
            body {
                background: white;
            }
            .receipt-card {
                box-shadow: none;
                border: none;
                padding: 0;
                margin: 0;
                width: 100%;
            }
        }
    </style>
</head>
<body>

    <div class="container">
        
        <!-- Action Toolbar (Hidden during Print) -->
        <div class="print-actions" style="max-width: 800px; margin: 2rem auto 0 auto;">
            <a href="orders.php" class="btn btn-outline" style="padding: 0.5rem 1rem; font-size: 0.9rem; text-decoration: none; border: 1px solid #ccc; border-radius: 4px; color: #333;">
                &larr; Back to Orders
            </a>
            <button onclick="window.print()" class="btn btn-accent" style="padding: 0.5rem 1.5rem; font-size: 0.9rem; background: #ff9800; color: white; border: none; border-radius: 4px; cursor: pointer;">
                🖨️ Print Receipt / Download PDF
            </button>
        </div>

        <!-- Printable Receipt Container -->
        <div class="receipt-card">
            
            <!-- Company & Receipt Header -->
            <div class="receipt-header">
                <div>
                    <h2 style="color: #1b5e20; font-weight: 800; font-size: 1.8rem; margin-bottom: 0.2rem;">
                        DanFam Market
                    </h2>
                    <p style="color: #64748b; font-size: 0.88rem; margin: 0; line-height: 1.4;">
                        Direct Smallholder Farmer-to-Buyer Marketplace<br>
                        Migori/kehancha Road, Kenya<br>
                        Email: support@danfammarket.co.ke | Tel: +254 700 123 456
                    </p>
                </div>
                <div style="text-align: right;">
                    <span class="badge" style="background: #e8f5e9; color: #1b5e20; font-size: 0.9rem; padding: 0.4rem 0.8rem; border-radius: 4px; font-weight: 700;">
                        OFFICIAL INVOICE
                    </span>
                    <h3 style="margin-top: 0.6rem; color: #0f172a; font-size: 1.1rem;">
                        #AGRI-<?= str_pad((string)$order['order_id'], 5, '0', STR_PAD_LEFT) ?>
                    </h3>
                </div>
            </div>

            <!-- Order Metadata & Customer Information -->
            <div class="receipt-meta">
                <div>
                    <span style="font-size: 0.8rem; text-transform: uppercase; color: #64748b; font-weight: 700; display: block; margin-bottom: 0.3rem;">Billed To</span>
                    <strong><?= htmlspecialchars($order['buyer_name']) ?></strong><br>
                    <span style="font-size: 0.9rem; color: #64748b;"><?= htmlspecialchars($order['buyer_email']) ?></span>
                </div>

                <div>
                    <span style="font-size: 0.8rem; text-transform: uppercase; color: #64748b; font-weight: 700; display: block; margin-bottom: 0.3rem;">Delivery Address</span>
                    <span style="font-size: 0.9rem; line-height: 1.4; display: block;">
                        <?= nl2br(htmlspecialchars($order['shipping_address'])) ?>
                    </span>
                </div>

                <div>
                    <span style="font-size: 0.8rem; text-transform: uppercase; color: #64748b; font-weight: 700; display: block; margin-bottom: 0.3rem;">Order Details</span>
                    <span style="font-size: 0.88rem;"><strong>Date:</strong> <?= date('d M Y, h:i A', strtotime($order['order_date'])) ?></span><br>
                    <span style="font-size: 0.88rem;"><strong>Status:</strong> <?= render_status_badge($order['order_status']) ?></span>
                </div>
            </div>

            <!-- Itemized Order Table -->
            <div style="margin-bottom: 2rem;">
                <h4 style="color: #0f172a; margin-bottom: 0.8rem; font-size: 1.1rem;">Order Items</h4>
                <div class="table-responsive">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="border-bottom: 2px solid #e2e8f0; text-align: left; background: #f8fafc;">
                                <th style="padding: 0.75rem;">Produce Item</th>
                                <th style="padding: 0.75rem; text-align: right;">Unit Price</th>
                                <th style="padding: 0.75rem; text-align: center;">Quantity</th>
                                <th style="padding: 0.75rem; text-align: right;">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($order_items as $item): ?>
                                <tr style="border-bottom: 1px solid #e2e8f0;">
                                    <td style="padding: 0.75rem;">
                                        <strong><?= htmlspecialchars($item['name']) ?></strong>
                                    </td>
                                    <td style="padding: 0.75rem; text-align: right;">
                                        <?= format_currency($item['unit_price']) ?>
                                    </td>
                                    <td style="padding: 0.75rem; text-align: center;">
                                        <?= $item['quantity'] ?> <?= htmlspecialchars($item['unit']) ?>
                                    </td>
                                    <td style="padding: 0.75rem; text-align: right; font-weight: 600;">
                                        <?= format_currency($item['subtotal']) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Invoice Total Summary -->
            <div style="display: flex; justify-content: flex-end; margin-top: 1.5rem; border-top: 2px solid #e2e8f0; padding-top: 1.25rem;">
                <div style="width: 280px; text-align: right;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem; font-size: 1rem;">
                        <span style="color: #64748b;">Total Amount Payable:</span>
                        <strong style="color: #1b5e20; font-size: 1.5rem;"><?= format_currency($order['total_amount']) ?></strong>
                    </div>
                    <small style="color: #64748b; font-size: 0.8rem; display: block;">Includes all applicable Kenyan agricultural tariffs.</small>
                </div>
            </div>

            <!-- Footer / Terms Note -->
            <div style="margin-top: 3rem; text-align: center; border-top: 1px dashed #e2e8f0; padding-top: 1.5rem; color: #64748b; font-size: 0.85rem;">
                <p style="margin-bottom: 0.3rem;">Thank you for supporting smallholder farming in Kenya with DanFam Market!</p>
                <p style="margin: 0;">This is a computer-generated document. No physical signature required.</p>
            </div>

        </div>
    </div>

</body>
</html>