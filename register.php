<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once 'config.php';
require_once 'functions.php';

// Redirect if already logged in
if (function_exists('is_logged_in') && is_logged_in()) {
    $role = $_SESSION['user_role'] ?? '';
    if ($role === 'Farmer') {
        redirect('farmer_dashboard.php');
    } elseif ($role === 'Administrator') {
        redirect('admin_dashboard.php');
    } else {
        redirect('catalogue.php');
    }
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_btn'])) {
    // Collect and sanitize inputs
    $full_name = sanitize_input($_POST['full_name'] ?? '');
    $email     = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $password  = $_POST['password'] ?? '';
    $role      = sanitize_input($_POST['role'] ?? 'Buyer');

    $allowed_roles = ['Buyer', 'Farmer', 'Administrator'];

    if (empty($full_name) || empty($email) || empty($password)) {
        $error_message = "Please fill in all required registration fields.";
    } elseif (!in_array($role, $allowed_roles, true)) {
        $error_message = "Invalid account role selected.";
    } elseif (strlen($password) < 6) {
        $error_message = "Password must be at least 6 characters long.";
    } else {
        // Strict admin limit check (Max 2 Administrators)
        if ($role === 'Administrator') {
            try {
                $adminCheckStmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'Administrator'");
                if ((int)$adminCheckStmt->fetchColumn() >= 2) {
                    $error_message = "Registration Failed: Maximum capacity of 2 administrators reached.";
                }
            } catch (PDOException $e) {
                error_log('[DanFam Market] Admin check error: ' . $e->getMessage());
            }
        }

        if (empty($error_message)) {
            // Calls register_user function with full_name
            $reg_result = register_user($pdo, $full_name, $email, $password, $role);
            if ($reg_result === true) {
                redirect('login.php?action=login&registered=1');
            } else {
                $error_message = (string) $reg_result;
            }
        }
    }
}
$page_title = "Register Account";
include 'header.php';
?>

<div class="container" style="max-width: 520px; margin-top: 2rem; margin-bottom: 5rem;">
    <div class="card card-body" style="padding: 2rem; border-top: 4px solid #1b5e20; background: white; border-radius: 8px; border: 1px solid #e2e8f0;">
        <h2 style="color: #0f172a; margin-bottom: 0.3rem;">Create Account</h2>
        <p style="color: #64748b; font-size: 0.9rem; margin-bottom: 1.5rem;">Join DanFam Market as a Farmer or Produce Buyer.</p>

        <?php if (!empty($error_message)): ?>
            <div style="background: #f8d7da; color: #721c24; padding: 0.85rem 1.2rem; border-radius: 6px; margin-bottom: 1.5rem; font-weight: 600; font-size: 0.9rem; border-left: 4px solid #d32f2f;">
                ⚠️ <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <form action="register.php" method="POST">
            <div class="form-group" style="margin-bottom: 1.2rem;">
                <label for="name" style="display: block; font-weight: 600; margin-bottom: 0.4rem; font-size: 0.9rem;">Full Name*</label>
                <input type="text" id="name" name="name" required placeholder="e.g. Samuel Mwangi" style="width: 100%; padding: 0.75rem; border: 1px solid #cbd5e1; border-radius: 6px;" value="<?= isset($_POST['full_name']) ? htmlspecialchars($_POST['name']) : '' ?>">
            </div>

            <div class="form-group" style="margin-bottom: 1.2rem;">
                <label for="email" style="display: block; font-weight: 600; margin-bottom: 0.4rem; font-size: 0.9rem;">Email Address *</label>
                <input type="email" id="email" name="email" required placeholder="e.g. mwangi@danfam.co.ke" style="width: 100%; padding: 0.75rem; border: 1px solid #cbd5e1; border-radius: 6px;" value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
            </div>

            <div class="form-group" style="margin-bottom: 1.2rem;">
                <label for="password" style="display: block; font-weight: 600; margin-bottom: 0.4rem; font-size: 0.9rem;">Password (Min. 6 Characters) *</label>
                <input type="password" id="password" name="password" required minlength="6" placeholder="••••••••" style="width: 100%; padding: 0.75rem; border: 1px solid #cbd5e1; border-radius: 6px;">
            </div>

            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label for="role" style="display: block; font-weight: 600; margin-bottom: 0.4rem; font-size: 0.9rem;"> Role *</label>
                <select id="role" name="role" required style="width: 100%; padding: 0.75rem; border: 1px solid #cbd5e1; border-radius: 6px; background: white;">
                    <option value="Buyer">🛒 Buyer (Household, Hotel, School,Supermarkets)</option>
                    <option value="Farmer">👨‍🌾 Smallholder Farmer (Producer / Seller)</option>
                    <option value="Administrator">⚙️ System Administrator</option>
                </select>
            </div>

            <button type="submit" name="register_btn" style="width: 100%; padding: 0.85rem; font-size: 1rem; font-weight: 700; background: #1b5e20; color: white; border: none; border-radius: 6px; cursor: pointer;">
                ✅ Create Account
            </button>
        </form>
    </div>
</div>

<?php include 'footer.php'; ?>