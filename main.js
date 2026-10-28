
const CART_STORAGE_KEY = 'agri_cart';

// Initialize core components on DOM load
document.addEventListener('DOMContentLoaded', () => {
    updateCartCountBadge();
    
    // Render cart table if on cart.php
    if (document.getElementById('cart-table-body')) {
        renderCartTable();
    }

    // Attach quick search handler if present on index/catalogue
    const quickSearchInput = document.getElementById('quick-search');
    if (quickSearchInput) {
        quickSearchInput.addEventListener('keyup', handleQuickSearch);
    }
});

// 1. CART MANAGEMENT FUNCTIONS
 

// Get current cart array from localStorage
function getCart() {
    try {
        const cartData = localStorage.getItem(CART_STORAGE_KEY);
        return cartData ? JSON.parse(cartData) : [];
    } catch (e) {
        console.error("Error reading cart from localStorage:", e);
        return [];
    }
}

// Save cart array to localStorage and refresh badge
function saveCart(cart) {
    localStorage.setItem(CART_STORAGE_KEY, JSON.stringify(cart));
    updateCartCountBadge();
}

// Add produce item to cart
function addToCart(produceId, produceName, unitPrice) {
    let cart = getCart();
    const existingIndex = cart.findIndex(item => item.id === produceId);

    if (existingIndex > -1) {
        cart[existingIndex].quantity += 1;
    } else {
        cart.push({
            id: produceId,
            name: produceName,
            price: parseFloat(unitPrice),
            quantity: 1
        });
    }

    saveCart(cart);
    showToast(`Added "${produceName}" to your shopping cart!`);
}

// Update badge count in header navigation
function updateCartCountBadge() {
    const badge = document.getElementById('cart-count');
    if (badge) {
        const cart = getCart();
        const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
        badge.textContent = totalItems;
    }
}

// Clear all items from cart
function clearCart() {
    if (confirm("Are you sure you want to clear all items from your cart?")) {
        localStorage.removeItem(CART_STORAGE_KEY);
        updateCartCountBadge();
        if (document.getElementById('cart-table-body')) {
            renderCartTable();
        }
    }
}

//  2. CART TABLE RENDERING (cart.php)
 

function renderCartTable() {
    const tableBody = document.getElementById('cart-table-body');
    const totalDisplay = document.getElementById('cart-total');
    const cart = getCart();

    if (!tableBody) return;

    if (cart.length === 0) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="5" style="text-align: center; padding: 2.5rem; color: var(--text-muted);">
                    🛒 Your shopping cart is currently empty. <br><br>
                    <a href="catalogue.php" class="btn btn-primary" style="font-size: 0.9rem;">Browse Produce Catalogue</a>
                </td>
            </tr>
        `;
        if (totalDisplay) totalDisplay.textContent = 'KSh 0.00';
        return;
    }

    let grandTotal = 0;
    let rowsHtml = '';

    cart.forEach((item, index) => {
        const subtotal = item.price * item.quantity;
        grandTotal += subtotal;

        rowsHtml += `
            <tr>
                <td><strong>${escapeHtml(item.name)}</strong></td>
                <td>KSh ${item.price.toFixed(2)}</td>
                <td>
                    <input type="number" min="1" value="${item.quantity}" 
                           onchange="updateQuantity(${index}, this.value)" 
                           style="width: 70px; padding: 0.3rem 0.5rem; border: 1px solid var(--gray-border); border-radius: var(--radius); text-align: center;">
                </td>
                <td style="font-weight: 700; color: var(--primary);">KSh ${subtotal.toFixed(2)}</td>
                <td style="text-align: center;">
                    <button onclick="removeFromCart(${index})" class="btn" style="background: #fee2e2; color: #dc2626; padding: 0.3rem 0.6rem; font-size: 0.8rem; border: none;">
                        ✖ Remove
                    </button>
                </td>
            </tr>
        `;
    });

    tableBody.innerHTML = rowsHtml;
    if (totalDisplay) {
        totalDisplay.textContent = `KSh ${grandTotal.toFixed(2)}`;
    }
}

function updateQuantity(index, newQty) {
    let cart = getCart();
    const qty = parseInt(newQty);

    if (isNaN(qty) || qty <= 0) {
        removeFromCart(index);
        return;
    }

    if (cart[index]) {
        cart[index].quantity = qty;
        saveCart(cart);
        renderCartTable();
    }
}

function removeFromCart(index) {
    let cart = getCart();
    if (cart[index]) {
        cart.splice(index, 1);
        saveCart(cart);
        renderCartTable();
    }
}

// 3. CHECKOUT PREPARATION (cart.php & checkout.php)
 

function prepareCheckout(event) {
    const cart = getCart();
    const cartInput = document.getElementById('cart_json');

    if (cart.length === 0) {
        alert("Your cart is empty! Please add fresh produce before proceeding to checkout.");
        event.preventDefault();
        return false;
    }

    if (cartInput) {
        cartInput.value = JSON.stringify(cart);
    }
    return true;
}

//  4. UI HELPERS & SEARCH
 

function handleQuickSearch(e) {
    const query = e.target.value.toLowerCase().trim();
    const cards = document.querySelectorAll('#homepage-grid .produce-card');

    cards.forEach(card => {
        const text = card.textContent.toLowerCase();
        if (text.includes(query)) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
}

function showToast(message) {
    let toast = document.getElementById('agri-toast');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'agri-toast';
        toast.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: var(--dark);
            color: white;
            padding: 0.8rem 1.4rem;
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
            z-index: 9999;
            font-size: 0.9rem;
            transition: opacity 0.3s ease;
        `;
        document.body.appendChild(toast);
    }

    toast.textContent = message;
    toast.style.opacity = '1';

    setTimeout(() => {
        toast.style.opacity = '0';
    }, 3000);
}

function escapeHtml(str) {
    return str.replace(/[&<>"']/g, function(m) {
        return {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        }[m];
    });
}