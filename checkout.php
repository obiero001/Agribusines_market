<?php
$page_title = "Checkout & Order Placement";
require_once 'functions.php';

// Authorization Guard: Require Buyer or logged-in user
require_login();

$error_message = '';
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'Buyer';

// Process POST form submission from checkout or cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $cart_json = $_POST['cart_json'] ?? '';
    $shipping_address = sanitize_input($_POST['address'] ?? '');
    
    $cart_items = json_decode($cart_json, true);

    if (empty($cart_items) || !is_array($cart_items)) {
        $error_message = "Your shopping cart is empty. Please add produce before checking out.";
    } elseif (empty($shipping_address)) {
        $error_message = "Please enter a valid delivery address across Kenya.";
    } else {
        try {
            $pdo->beginTransaction();

            // Calculate Total & Verify Stock Availability
            $total_amount = 0;
            $verified_items = [];

            foreach ($cart_items as $item) {
                $produce_id = intval($item['id'] ?? 0);
                $quantity = floatval($item['quantity'] ?? 0);
                $price = floatval($item['price'] ?? 0);

                if ($produce_id <= 0 || $quantity <= 0) {
                    throw new Exception("Invalid item data in cart.");
                }

                // Check produce status and available stock
                $stmt_check = $pdo->prepare("SELECT price_per_unit, quantity_available FROM produce WHERE produce_id = ? AND status = 'Approved'");
                $stmt_check->execute([$produce_id]);
                $produce = $stmt_check->fetch();

                if (!$produce || floatval($produce['quantity_available']) < $quantity) {
                    throw new Exception("One or more items in your cart are out of stock or no longer available.");
                }

                $subtotal = $price * $quantity; // Fixed multiplication syntax
                $total_amount += $subtotal;

                $verified_items[] = [
                    'produce_id' => $produce_id,
                    'quantity' => $quantity,
                    'price' => $price,
                    'subtotal' => $subtotal
                ];
            }

            // 1. Insert Master Order (Using 'order_status' matching database schema)
            $stmt = $pdo->prepare("INSERT INTO orders (buyer_id, total_amount, shipping_address, order_status) VALUES (?, ?, ?, 'Placed')");
            $stmt->execute([$user_id, $total_amount, $shipping_address]);
            $order_id = $pdo->lastInsertId();

            // 2. Insert Line Items & Deduct Stock
            $stmt_item = $pdo->prepare("INSERT INTO order_items (order_id, produce_id, quantity, price_at_purchase) VALUES (?, ?, ?, ?)");
            $stmt_stock = $pdo->prepare("UPDATE produce SET quantity_available = quantity_available - ? WHERE produce_id = ?");

            foreach ($verified_items as $item) {
                $stmt_item->execute([
                    $order_id,
                    $item['produce_id'],
                    $item['quantity'],
                    $item['price']
                ]);

                $stmt_stock->execute([
                    $item['quantity'],
                    $item['produce_id']
                ]);
            }

            $pdo->commit();

            // Clear local storage cart and redirect to receipt
            echo "<script>
                    localStorage.removeItem('agri_cart');
                    window.location.href = 'receipt.php?order_id=" . $order_id . "';
                  </script>";
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error_message = "Order placement failed: " . $e->getMessage();
        }
    }
}

include 'header.php';
?>

<div class="container" style="max-width: 800px; margin-top: 2.5rem; margin-bottom: 4rem;">
    
    <!-- Page Breadcrumb Navigation -->
    <div style="margin-bottom: 1.5rem;">
        <a href="cart.php" class="btn btn-outline" style="font-size: 0.88rem; padding: 0.4rem 0.8rem;">
            &larr; Back to Shopping Cart
        </a>
    </div>

    <div class="card card-body" style="padding: 2.5rem; border-top: 5px solid var(--primary, #1b5e20);">
        <h2 style="color: var(--dark, #0f172a); margin-bottom: 0.5rem; display: flex; align-items: center; gap: 8px;">
            Order Checkout
        </h2>
        <p style="color: var(--text-muted, #64748b); font-size: 0.95rem; margin-bottom: 2rem;">
            Review your order summary and confirm delivery information below.
        </p>

        <?php if (!empty($error_message)): ?>
            <div style="background: #f8d7da; color: #721c24; padding: 1rem 1.25rem; border-radius: var(--radius, 6px); margin-bottom: 1.5rem; font-weight: 600; border-left: 4px solid #d32f2f;">
                ⚠️ <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <!-- Form: Delivery Address & Cart Payload -->
        <form action="checkout.php" method="POST" id="checkout-form" onsubmit="return syncCartToForm(event)">
            <input type="hidden" name="cart_json" id="cart_json">
            <input type="hidden" name="place_order" value="1">

            <!-- Customer Account Details -->
            <div style="background: var(--gray-light, #f8fafc); padding: 1.25rem; border-radius: var(--radius, 6px); margin-bottom: 1.5rem; border: 1px solid var(--gray-border, #e2e8f0);">
                <h4 style="margin-bottom: 0.5rem; color: var(--dark, #0f172a);">👤 Customer Information</h4>
                <p style="margin: 0; font-size: 0.9rem; color: var(--text-muted, #64748b);">
                    <strong>Name:</strong> <?= htmlspecialchars($user_name) ?><br>
                    <strong>Email:</strong> <?= htmlspecialchars($_SESSION['user_email'] ?? 'Verified Account') ?>
                </p>
            </div>

            <!-- Delivery Address Input -->
            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label for="address" style="display: block; font-weight: 600; margin-bottom: 0.5rem; color: var(--dark, #0f172a);">
                    Delivery Address / Location in Kenya *
                </label>
                <textarea name="address" id="address" required rows="3" class="form-control" placeholder="e.g. Thika Town, Commercial Street, Plaza Building, Door 4" style="width: 100%; padding: 0.8rem; border: 1px solid var(--gray-border, #e2e8f0); border-radius: var(--radius, 6px); font-size: 0.95rem;"></textarea>
                <small style="color: var(--text-muted, #64748b); font-size: 0.85rem; display: block; margin-top: 0.3rem;">
                    Specify your preferred delivery spot (Hotel, Household, School, or Market Hub).
                </small>
            </div>

            <!-- Order Items Preview -->
            <div style="margin-bottom: 2rem;">
                <h4 style="margin-bottom: 0.8rem; color: var(--dark, #0f172a);">Items in Checkout</h4>
                <div id="checkout-items-preview" style="background: white; border: 1px solid var(--gray-border, #e2e8f0); border-radius: var(--radius, 6px); padding: 1rem;">
                    <!-- Dynamically populated via JS -->
                </div>
            </div>

            <!-- Action Buttons -->
            <div style="display: flex; justify-content: space-between; align-items: center; border-top: 2px dashed var(--gray-border, #e2e8f0); padding-top: 1.5rem;">
                <a href="cart.php" style="color: var(--text-muted, #64748b); text-decoration: none; font-size: 0.9rem;">
                    Edit Cart
                </a>
                <button type="submit" class="btn btn-accent" style="padding: 0.85rem 2.5rem; font-size: 1.05rem; font-weight: 700; background: #1b5e20; color: white; border: none; border-radius: 6px; cursor: pointer;">
                    ✅ Place Order & Generate Invoice
                </button>
            </div>
        </form>
    </div>
</div>

<script src="main.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const cart = JSON.parse(localStorage.getItem('agri_cart')) || [];
        const previewContainer = document.getElementById('checkout-items-preview');

        if (cart.length === 0) {
            previewContainer.innerHTML = '<p style="color:red; margin:0;">Your cart is empty. Please add items from the <a href="catalogue.php">Catalogue</a>.</p>';
            return;
        }

        let total = 0;
        let html = '<ul style="list-style:none; padding:0; margin:0;">';
        cart.forEach(item => {
            let subtotal = Number(item.price) * Number(item.quantity);
            total += subtotal;
            html += `<li style="display:flex; justify-content:space-between; padding:0.5rem 0; border-bottom:1px solid #f1f5f9;">
                        <span><strong>${item.name}</strong> x ${item.quantity}</span>
                        <span>KSh ${subtotal.toFixed(2)}</span>
                     </li>`;
        });
        html += `</ul><div style="margin-top:0.8rem; text-align:right; font-weight:700; font-size:1.1rem; color:var(--primary, #1b5e20);">Total: KSh ${total.toFixed(2)}</div>`;
        
        previewContainer.innerHTML = html;
    });

    function syncCartToForm(e) {
        const cart = JSON.parse(localStorage.getItem('agri_cart')) || [];
        if (cart.length === 0) {
            alert('Your cart is empty. Please select produce first.');
            e.preventDefault();
            return false;
        }
        document.getElementById('cart_json').value = JSON.stringify(cart);
        return true;
    }
</script>

<?php include 'footer.php'; ?>