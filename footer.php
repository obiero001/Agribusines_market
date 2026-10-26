<?php
?>
<style>
    .site-footer {
        background: linear-gradient(135deg, #f2f9f1 0%, #e3f2e1 100%);
        color: #1e293b;
        border-top: 4px solid var(--primary, #1b5e20);
        margin-top: 4rem;
        box-shadow: 0 -4px 12px rgba(0, 0, 0, 0.03);
        font-family: inherit;
    }

    .footer-content {
        max-width: 1200px;
        margin: 0 auto;
        padding: 3.5rem 1.5rem 2.5rem 1.5rem;
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
        gap: 2.5rem;
    }

    .footer-section h3 {
        color: #1b5e20;
        margin-bottom: 0.75rem;
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 1.35rem;
        font-weight: 700;
    }

    .footer-section h4 {
        color: #1b5e20;
        margin-bottom: 1rem;
        font-size: 1.1rem;
        font-weight: 700;
        position: relative;
        padding-bottom: 0.4rem;
    }

    .footer-section h4::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        width: 30px;
        height: 2px;
        background: var(--accent, #f57c00);
        border-radius: 2px;
    }

    .footer-text {
        color: #334155;
        font-size: 0.92rem;
        line-height: 1.6;
        margin-bottom: 1rem;
    }

    .footer-links {
        list-style: none;
        display: flex;
        flex-direction: column;
        gap: 0.6rem;
        padding: 0;
        margin: 0;
    }

    .footer-links a {
        color: #1e293b;
        text-decoration: none;
        font-size: 0.92rem;
        font-weight: 500;
        transition: all 0.2s ease-in-out;
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
    }

    .footer-links a:hover {
        color: var(--accent, #f57c00);
        transform: translateX(4px);
    }

    .footer-contact-item {
        color: #334155;
        font-size: 0.92rem;
        margin-bottom: 0.5rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .footer-contact-item a {
        color: #1e293b;
        text-decoration: none;
        transition: color 0.2s;
    }

    .footer-contact-item a:hover {
        color: var(--accent, #f57c00);
        text-decoration: underline;
    }

    .footer-bottom {
        background-color: #d8ebd4;
        border-top: 1px solid #c7e1c2;
        text-align: center;
        padding: 1.25rem 1.5rem;
        color: #334155;
        font-size: 0.88rem;
        font-weight: 500;
    }

    .footer-bottom a {
        color: #1b5e20;
        text-decoration: none;
        font-weight: 600;
    }

    .footer-bottom a:hover {
        text-decoration: underline;
    }
</style>

<footer class="site-footer">
    <div class="footer-content">
        <!-- Brand / Mission Section -->
        <div class="footer-section">
            <h3>DanFam market</h3>
            <p class="footer-text">
                Bridging the gap between smallholder farmers, fishers, and urban markets. Direct farm-to-table and lake-to-buyer supply chains for households, hotels, schools, and exporters across Kenya.
            </p>
            <span style="background: #d8ebd4; color: #1b5e20; font-size: 0.78rem; padding: 0.3rem 0.75rem; border-radius: 50px; font-weight: 600; border: 1px solid #c7e1c2; display: inline-block;">
                DanFam-Empowering Kenyan Agriculture
            </span>
        </div>

        <!-- Quick Navigation -->
        <div class="footer-section">
            <h4>Quick Links</h4>
            <ul class="footer-links">
                <li><a href="index.html">Home</a></li>
                <li><a href="catalogue.php">Produce Catalogue</a></li>
                <li><a href="cart.php">Shopping Cart</a></li>
                <li><a href="orders.php">Track Orders</a></li>
                <li><a href="login.php">🔐 User Login </a></li>
                <li><a href="login.php"> Register</a></li>
            </ul>
        </div>

        <!-- Contact & Location -->
        <div class="footer-section">
            <h4>Contact & Location</h4>
            <div class="footer-contact-item">
                <span>📍</span>
                <span>Migori / Kehancha-Kisii Road, Kenya</span>
            </div>
            <div class="footer-contact-item">
                <span>📧</span>
                <a href="mailto:support@agriconnect.co.ke">support@DanFam.co.ke</a>
            </div>
            <div class="footer-contact-item">
                <span>📞</span>
                <span>+254 700 123 456</span>
            </div>
            <div style="margin-top: 0.75rem;">
                <small style="color: #64748b; font-size: 0.8rem;">Mon - Sat: 7:00 AM - 6:00 PM EAT</small>
            </div>
        </div>
    </div>

    <!-- Copyright Bar -->
    <div class="footer-bottom">
        <div class="container" style="max-width: 1200px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
            <p style="margin: 0;">&copy; <?= date('Y') ?> DanFam Market. All Rights Reserved.</p>
            
        </div>
    </div>
</footer>

<!-- Include Global Client-Side Script -->
<script src="main.js"></script>
</body>
</html>