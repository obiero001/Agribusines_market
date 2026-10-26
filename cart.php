<?php

$page_title = "Shopping Cart & Checkout";
require_once 'functions.php';
include 'header.php';
?>

<div class="container" style="max-width: 950px; margin-top: 2rem; margin-bottom: 3rem;">
    <!-- Page Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; border-bottom: 2px solid var(--gray-border); padding-bottom: 1rem;">
        <div>
            <h2 style="color: var(--dark); margin-bottom: 0.2rem;">🛒 Shopping Cart</h2>
            <p style="color: var(--text-muted); font-size: 0.95rem;">Review your selected agricultural produce before placing your order.</p>
        </div>
        <a href="catalogue.php" class="btn btn-outline" style="font-size: 0.88rem; padding: 0.5rem 1rem;">
            &larr; Continue Shopping
        </a>
    </div>

    <!-- Cart Table -->
    <div class="card card-body" style="padding: 1.5rem; margin-bottom: 2rem;">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Produce Item</th>
                        <th>Unit price</th>
                        <th style="width: 120px;">Quantity</th>
                        <th>Subtotal</th>
                        <th style="text-align: center;">Action</th>
                    </tr>
                </thead>
                <tbody id="cart-table-body">
                    <!-- Populated dynamically via JavaScript from main.js -->
                </tbody>
            </table>
        </div>

        <!-- Total Summary Row -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 1.5rem; padding-top: 1.5rem; border-top: 2px dashed var(--gray-border);">
            <div>
                <button onclick="clearCart()" class="btn btn-outline" style="color: #d32f2f; border-color: #f8d7da; font-size: 0.85rem; padding: 0.4rem 0.8rem;">
                    🗑️ Clear Cart
                </button>
            </div>
            <div style="text-align: right;">
                <span style="font-size: 1rem; color: var(--text-muted);">Total Payable Amount:</span>
                <h2 id="cart-total" style="color: var(--primary); font-size: 2rem; margin-top: 0.2rem;">KSh 0.00</h2>
            </div>
        </div>
    </div>

    <!-- Checkout Details Section -->
    <div class="card card-body" style="padding: 2rem; border-top: 4px solid var(--accent);">
        <h3 style="margin-bottom: 0.5rem; color: var(--dark);">🚚 Delivery & Order Confirmation</h3>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1.5rem;">Please provide your delivery destination address across Kenya to complete the order.</p>

        <?php if (!is_logged_in()): ?>
            <div style="background: #fff3cd; color: #856404; padding: 1rem 1.25rem; border-radius: var(--radius); border-left: 4px solid #ffeeba; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <strong>🔒 Login Required to Checkout</strong>
                    <p style="font-size: 0.88rem; margin-top: 0.2rem;">You must sign in as a Buyer to place this order.</p>
                </div>
                <a href="login.php?action=login" class="btn btn-accent" style="padding: 0.5rem 1.2rem; font-size: 0.9rem;">
                    Sign In to Order &rarr;
                </a>
            </div>
        <?php else: ?>
            <form action="cart.php" method="POST" onsubmit="return prepareCheckout(event)">
                <input type="hidden" name="cart_json" id="cart_json">
                
                <div class="form-group" style="margin-bottom: 1.25rem;">
                    <label for="address" style="display: block; font-weight: 600; margin-bottom: 0.5rem; color: var(--dark);">
                        Delivery Address / Location (County, Town, Building)
                    </label>
                    <textarea name="address" id="address" required rows="3" class="form-control" placeholder="e.g. Thika Town, Commercial Street, Block B, Kiambu County" style="width: 100%; padding: 0.75rem; border: 1px solid var(--gray-border); border-radius: var(--radius); font-size: 0.95rem;"></textarea>
                </div>

                <div style="display: flex; justify-content: flex-end; align-items: center; gap: 1rem; margin-top: 1.5rem;">
                    <button type="submit" name="place_order" class="btn btn-accent" style="padding: 0.85rem 2.5rem; font-size: 1.05rem; font-weight: 700;">
                        ✅ Confirm & Place Order
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php
// Handle Backend Order Processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cart_json'])) {
    if (!is_logged_in()) {
        redirect('login.php');
    }

    $cart_data = json_decode($_POST['cart_json'], true);
    $address = sanitize_input($_POST['address']);
    $buyer_id = $_SESSION['user_id'];
    $total_amount = 0;

    if (!empty($cart_data) && is_array($cart_data)) {
        foreach ($cart_data as $item) {
            $total_amount += floatval($item['price']) * intval($item['quantity']);
        }

        try {
            $pdo->beginTransaction();

            // 1. Insert Master Order Record
            $stmt = $pdo->prepare("INSERT INTO orders (buyer_id, total_amount, shipping_address, status) VALUES (?, ?, ?, 'Placed')");
            $stmt->execute([$buyer_id, $total_amount, $address]);
            $order_id = $pdo->lastInsertId();

            // 2. Insert Order Item Breakdowns
            $stmt_item = $pdo->prepare("INSERT INTO order_items (order_id, produce_id, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?)");
            foreach ($cart_data as $item) {
                $subtotal = floatval($item['price']) * intval($item['quantity']);
                $stmt_item->execute([
                    $order_id,
                    intval($item['id']),
                    floatval($item['quantity']),
                    floatval($item['price']),
                    $subtotal
                ]);
            }

            $pdo->commit();
            
            // Redirect to Printable Order Receipt
            echo "<script>
                    localStorage.removeItem('agri_cart');
                    window.location.href = 'receipt.php?order_id=" . $order_id . "';
                  </script>";
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            echo "<script>alert('Order processing failed: " . addslashes($e->getMessage()) . "');</script>";
        }
    }
}

include 'footer.php';
?>