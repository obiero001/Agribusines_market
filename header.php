<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'functions.php';

// Determine active page for highlighting navigation links
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title><?= isset($page_title) ? htmlspecialchars($page_title) . ' - DanFam Market' : 'DanFam Market' ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Header Layout & Navigation Distribution */
        .main-header {
            background: #ffffff;
            border-bottom: 1px solid var(--gray-border);
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 4px rgba(0,0,0,0.04);
        }

        .header-wrapper {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.85rem 1.5rem;
            max-width: 1200px;
            margin: 0 auto;
        }

        .brand-logo {
            font-size: 1.35rem;
            font-weight: 700;
            color: var(--primary);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            letter-spacing: -0.3px;
        }

        .brand-badge {
            font-size: 0.72rem;
            background: var(--primary-soft);
            color: var(--primary);
            padding: 2px 8px;
            border-radius: 12px;
            font-weight: 600;
        }

        .nav-menu {
            display: flex;
            align-items: center;
            gap: 1.25rem;
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .nav-link {
            color: var(--dark);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.95rem;
            padding: 0.4rem 0.75rem;
            border-radius: var(--radius);
            transition: all 0.2s ease-in-out;
        }

        .nav-link:hover, .nav-link.active {
            color: var(--primary);
            background: var(--primary-soft);
        }

        .cart-badge-counter {
            background: var(--accent);
            color: white;
            font-size: 0.75rem;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 50px;
            margin-left: 4px;
        }

        .user-actions {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .user-pill {
            font-size: 0.85rem;
            background: var(--gray-light);
            color: var(--dark);
            padding: 0.35rem 0.8rem;
            border-radius: 50px;
            border: 1px solid var(--gray-border);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        @media (max-width: 868px) {
            .header-wrapper {
                flex-direction: column;
                gap: 0.8rem;
                padding: 1rem;
            }
            .nav-menu {
                flex-wrap: wrap;
                justify-content: center;
                gap: 0.5rem;
            }
            .nav-link {
                font-size: 0.88rem;
            }
        }
    </style>
</head>
<body>

<header class="main-header">
    <div class="header-wrapper">
        <!-- 1. Brand / Logo -->
            <span>DanFam Market</span>            
        </a>
        <!-- 2. Distributed Navigation Links -->
        <nav>
            <ul class="nav-menu">
                                   <li><a href="index.html" class="nav-link active">Home</a></li>
                    <li><a href="catalogue.php" class="nav-link active"> Catalogue</a></li>
                    <li><a href="farmer_dashboard.php" class="nav-link active">Farmer Portal</a></li>
                    <li><a href="admin_dashboard.php" class="nav-link active">⚙️ Admin</a></li>
                    
                <!-- Role-Based Navigation Routing -->
                <?php if (is_logged_in()): ?>
                    <?php if ($_SESSION['user_role'] === 'Farmer'): ?>
                        <li>
                            <a href="farmer_dashboard.php" class="nav-link <?= $current_page === 'farmer_dashboard.php' ? 'active' : '' ?>">
                                Farmer Portal
                            </a>
                        </li>
                        <li>
                            <a href="form.php" class="nav-link <?= $current_page === 'form.php' ? 'active' : '' ?>">
                                ➕ Post Produce
                            </a>
                        </li>
                    <?php elseif ($_SESSION['user_role'] === 'Administrator'): ?>
                        <li>
                            <a href="admin_dashboard.php" class="nav-link <?= $current_page === 'admin_dashboard.php' ? 'active' : '' ?>">
                                ⚙️ Admin Console
                            </a>
                            
                        </li>
                    <?php endif; ?>
                <?php endif; ?>

                <li>
                    <a href="cart.php" class="nav-link <?= $current_page === 'cart.php' ? 'active' : '' ?>">
                        🛒 Cart <span id="cart-count" class="cart-badge-counter">0</span>
                    </a>
                </li>
            </ul>
        </nav>

        <!-- 3. User Authentication State & Profile Action Pills -->
        <div class="user-actions">
            <?php if (is_logged_in()): ?>
                <div class="user-pill">
                    <span>👤</span>
                    <strong><?= htmlspecialchars($_SESSION['user_name']) ?></strong>
                    <small style="color: var(--text-muted);">(<?= htmlspecialchars($_SESSION['user_role']) ?>)</small>
                </div>
                <a href="logout.php" class="btn btn-outline" style="padding: 0.35rem 0.8rem; font-size: 0.85rem; border-color: var(--gray-border);">
                    Logout
                </a>
            <?php else: ?>
                <a href="login.php?action=login" class="btn btn-outline" style="padding: 0.4rem 0.9rem; font-size: 0.9rem;">
                    Sign In
                </a>
                <a href="login.php?action=register" class="btn btn-accent" style="padding: 0.4rem 0.9rem; font-size: 0.9rem;">
                    Register
                </a>
            <?php endif; ?>
        </div>
    </div>
</header>