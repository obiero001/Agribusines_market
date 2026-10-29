<?php
$page_title = "Shopping Cart & Checkout";
require_once 'functions.php';

// Handle Backend Order Processing BEFORE sending any HTML headers
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cart_json'])) {
    if (!is_logged_in()) {
        redirect('login.php');
    }

    $cart_data = json_decode($_POST['cart_json'], true);
    $address = sanitize_input($_POST['address'] ?? '');
    $buyer_id = $_SESSION['user_id'];
    $total_amount = 0;

    if (!empty($cart_data) && is_array($cart_data)) {
        foreach ($cart_data as $item) {
            $total_amount += floatval($item['price']) * floatval($item['quantity']);
        }

        try {
            $pdo->beginTransaction();

            // 1. Insert Master Order Record
            $stmt = $pdo->prepare("INSERT INTO orders (buyer_id, total_amount, shipping_address, order_status) VALUES (?, ?, ?, 'Placed')");
            $stmt->execute([$buyer_id, $total_amount, $address]);

            // FIX 1: Retrieve lastInsertId BEFORE commit() so it returns the true order_id
            $order_id = $pdo->lastInsertId();

            // FIX 2: Aligned columns with database schema (price_at_purchase)
            $stmt_item = $pdo->prepare("INSERT INTO order_items (order_id, produce_id, quantity, price_at_purchase) VALUES (?, ?, ?, ?)");
            $stmt_stock = $pdo->prepare("UPDATE produce SET quantity_available = quantity_available - ? WHERE produce_id = ?");

            foreach ($cart_data as $item) {
                $produce_id = intval($item['id']);
                $qty = floatval($item['quantity']);
                $price = floatval($item['price']);

                $stmt_item->execute([
                    $order_id,
                    $produce_id,
                    $qty,
                    $price
                ]);

                // Deduct stock availability
                $stmt_stock->execute([$qty, $produce_id]);
            }

            // Commit transaction after inserting all order items and getting the ID
            $pdo->commit();
            
            // Clear browser local storage cart and redirect to receipt with valid ID
            include 'header.php';
            echo "<script>
                    localStorage.removeItem('agri_cart');
                    window.location.href = 'receipt.php?order_id=" . $order_id . "';
                  </script>";
            include 'footer.php';
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[Checkout Error] ' . $e->getMessage());
        }
    }
}

include 'header.php';
?>

<div class="container" style="max-width: 950px; margin-top: 2rem; margin-bottom: 3rem;">
    <!-- Page Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; border-bottom: 2px solid var(--gray-border, #e2e8f0); padding-bottom: 1rem;">
        <div>
            <h2 style="color: var(--dark, #0f172a); margin-bottom: 0.2rem;">🛒 Shopping Cart</h2>
            <p style="color: var(--text-muted, #64748b); font-size: 0.95rem;">Review your selected agricultural produce before placing your order.</p>
        </div>
        <a href="catalogue.php" class="btn btn-outline" style="font-size: 0.88rem; padding: 0.5rem 1rem; text-decoration: none; border: 1px solid #cbd5e1; border-radius: 6px; color: #0f172a;">
            &larr; Continue Shopping
        </a>
    </div>

    <!-- Cart Table -->
    <div class="card card-body" style="padding: 1.5rem; margin-bottom: 2rem; background: white; border: 1px solid #e2e8f0; border-radius: 8px;">
        <div class="table-responsive">
            <table style="width: 100%; border-collapse: collapse; text-align: left;">
                <thead>
                    <tr style="border-bottom: 2px solid #e2e8f0; font-size: 0.88rem; color: #64748b;">
                        <th style="padding: 0.75rem;">Produce Item</th>
                        <th style="padding: 0.75rem;">Unit Price</th>
                        <th style="padding: 0.75rem; width: 120px;">Quantity</th>
                        <th style="padding: 0.75rem;">Subtotal</th>
                        <th style="padding: 0.75rem; text-align: center;">Action</th>
                    </tr>
                </thead>
                <tbody id="cart-table-body">
                    <!-- Populated dynamically via JavaScript -->
                </tbody>
            </table>
        </div>

        <!-- Total Summary Row -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 1.5rem; padding-top: 1.5rem; border-top: 2px dashed var(--gray-border, #e2e8f0);">
            <div>
                <button onclick="clearCart()" class="btn btn-outline" style="color: #d32f2f; border: 1px solid #f8d7da; background: #fff5f5; font-size: 0.85rem; padding: 0.4rem 0.8rem; border-radius: 6px; cursor: pointer;">
                    🗑️ Clear Cart
                </button>
            </div>
            <div style="text-align: right;">
                <span style="font-size: 1rem; color: var(--text-muted, #64748b);">Total Payable Amount:</span>
                <h2 id="cart-total" style="color: var(--primary, #1b5e20); font-size: 2rem; margin-top: 0.2rem;">KSh 0.00</h2>
            </div>
        </div>
    </div>

    <!-- Checkout Details Section -->
    <div class="card card-body" style="padding: 2rem; border-top: 4px solid var(--accent, #1b5e20); background: white; border-radius: 8px; border-left: 1px solid #e2e8f0; border-right: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0;">
        <h3 style="margin-bottom: 0.5rem; color: var(--dark, #0f172a);">🚚 Delivery & Order Confirmation</h3>
        <p style="color: var(--text-muted, #64748b); font-size: 0.9rem; margin-bottom: 1.5rem;">Please provide your delivery destination address across Kenya to complete the order.</p>

        <?php if (!is_logged_in()): ?>
            <div style="background: #fff3cd; color: #856404; padding: 1rem 1.25rem; border-radius: 6px; border-left: 4px solid #ffeeba; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <strong>🔒 Login Required to Checkout</strong>
                    <p style="font-size: 0.88rem; margin-top: 0.2rem;">You must sign in as a Buyer to place this order.</p>
                </div>
                <a href="login.php?action=login" class="btn btn-accent" style="padding: 0.5rem 1.2rem; font-size: 0.9rem; background: #1b5e20; color: white; border-radius: 6px; text-decoration: none;">
                    Sign In to Order &rarr;
                </a>
            </div>
        <?php else: ?>
            <form action="cart.php" method="POST" onsubmit="return prepareCheckout(event)">
                <input type="hidden" name="cart_json" id="cart_json">
                
                <div class="form-group" style="margin-bottom: 1.25rem;">
                    <label for="address" style="display: block; font-weight: 600; margin-bottom: 0.5rem; color: var(--dark, #0f172a);">
                        Delivery Address / Location (County, Town, Building)
                    </label>
                    <textarea name="address" id="address" required rows="3" class="form-control" placeholder="e.g. Thika Town, Commercial Street, Block B, Kiambu County" style="width: 100%; padding: 0.75rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.95rem;"></textarea>
                </div>

                <div style="display: flex; justify-content: flex-end; align-items: center; gap: 1rem; margin-top: 1.5rem;">
                    <button type="submit" name="place_order" class="btn btn-accent" style="padding: 0.85rem 2.5rem; font-size: 1.05rem; font-weight: 700; background: #1b5e20; color: white; border: none; border-radius: 6px; cursor: pointer;">
                        ✅ Confirm Order
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<script src="main.js"></script>
<script>
    document.addEventListener("DOMContentLoaded", function () {
        renderCart();
    });

    function renderCart() {
        let cart = JSON.parse(localStorage.getItem('agri_cart')) || [];
        let tbody = document.getElementById('cart-table-body');
        let totalDisplay = document.getElementById('cart-total');

        if (cart.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="5" style="text-align: center; padding: 2.5rem; color: #64748b;">
                        Your cart is empty. <a href="catalogue.php" style="color: #1b5e20; font-weight: 600;">Browse produce catalogue</a>
                    </td>
                </tr>
            `;
            totalDisplay.innerText = "KSh 0.00";
            return;
        }

        let html = '';
        let grandTotal = 0;

        cart.forEach((item, index) => {
            let subtotal = parseFloat(item.price) * parseFloat(item.quantity);
            grandTotal += subtotal;

            html += `
                <tr style="border-bottom: 1px solid #f1f5f9;">
                    <td style="padding: 0.85rem; font-weight: 600; color: #0f172a;">${escapeHtml(item.name)}</td>
                    <td style="padding: 0.85rem; color: #334155;">KSh ${parseFloat(item.price).toFixed(2)}</td>
                    <td style="padding: 0.85rem;">
                        <input type="number" value="${item.quantity}" min="1" style="width: 70px; padding: 0.35rem; border: 1px solid #cbd5e1; border-radius: 4px; text-align: center;" onchange="updateQuantity(${index}, this.value)">
                    </td>
                    <td style="padding: 0.85rem; font-weight: 700; color: #1b5e20;">KSh ${subtotal.toFixed(2)}</td>
                    <td style="padding: 0.85rem; text-align: center;">
                        <button onclick="removeItem(${index})" style="background: #ef4444; color: white; border: none; padding: 0.35rem 0.7rem; border-radius: 4px; cursor: pointer; font-size: 0.8rem;">Remove</button>
                    </td>
                </tr>
            `;
        });

        tbody.innerHTML = html;
        totalDisplay.innerText = `KSh ${grandTotal.toFixed(2)}`;
    }

    function updateQuantity(index, newQty) {
        let cart = JSON.parse(localStorage.getItem('agri_cart')) || [];
        let qty = parseFloat(newQty);

        if (qty > 0) {
            cart[index].quantity = qty;
        } else {
            cart.splice(index, 1);
        }

        localStorage.setItem('agri_cart', JSON.stringify(cart));
        renderCart();
        if (typeof updateCartBadge === 'function') {
            updateCartBadge();
        }
    }

    function removeItem(index) {
        let cart = JSON.parse(localStorage.getItem('agri_cart')) || [];
        cart.splice(index, 1);
        localStorage.setItem('agri_cart', JSON.stringify(cart));
        renderCart();
        if (typeof updateCartBadge === 'function') {
            updateCartBadge();
        }
    }

    function clearCart() {
        if (confirm('Are you sure you want to clear all items from your cart?')) {
            localStorage.removeItem('agri_cart');
            renderCart();
            if (typeof updateCartBadge === 'function') {
                updateCartBadge();
            }
        }
    }

    function prepareCheckout(e) {
        let cart = JSON.parse(localStorage.getItem('agri_cart')) || [];
        if (cart.length === 0) {
            alert('Your cart is empty. Please add items from the catalogue first.');
            e.preventDefault();
            return false;
        }

        document.getElementById('cart_json').value = JSON.stringify(cart);
        return true;
    }

    function escapeHtml(str) {
        return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }
</script>

<?php include 'footer.php'; ?>