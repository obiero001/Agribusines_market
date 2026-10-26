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
$success_message = '';

// Handle Registration Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_btn'])) {
    $name     = sanitize_input($_POST['name'] ?? '');
    $email    = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';
    $role     = sanitize_input($_POST['role'] ?? 'Buyer');

    $allowed_roles = ['Buyer', 'Farmer', 'Administrator'];

    if (empty($name) || empty($email) || empty($password)) {
        $error_message = "Please fill in all required registration fields.";
    } elseif (!in_array($role, $allowed_roles, true)) {
        $error_message = "Invalid account role selected.";
    } elseif (strlen($password) < 6) {
        $error_message = "Password must be at least 6 characters long.";
    } else {
        // 🛑 STRICT ADMIN LIMIT CHECK (Max 2 Administrators)
        if ($role === 'Administrator') {
            try {
                $adminCheckStmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'Administrator' OR user_role = 'Administrator'");
                $currentAdminCount = (int) $adminCheckStmt->fetchColumn();

                if ($currentAdminCount >= 2) {
                    $error_message = "Registration Failed: The system has reached its maximum capacity of 2 administrators.";
                }
            } catch (PDOException $e) {
                error_log('[AgriConnect register.php] Admin check error: ' . $e->getMessage());
            }
        }

        // Proceed if no error was raised during the admin check
        if (empty($error_message)) {
            $reg_result = register_user($pdo, $name, $email, $password, $role);
            if ($reg_result === true) {
                redirect('login.php?action=login&registered=1');
            } else {
                $error_message = (string) $reg_result;
            }
        }
    }
}

// Check current administrator count for UI dropdown rendering
try {
    $adminCountStmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'Administrator' OR user_role = 'Administrator'");
    $adminCount = (int) $adminCountStmt->fetchColumn();
} catch (PDOException $e) {
    $adminCount = 0;
}

$page_title = "Register Account";
include 'header.php';
?>

<?php if (isset($_SESSION['reg_error'])): ?>
    <div class="container" style="max-width: 520px; margin-top: 1.5rem;">
        <div style="background: #f8d7da; color: #721c24; padding: 0.85rem 1.2rem; border-radius: var(--radius, 6px); font-weight: 600; font-size: 0.9rem; border-left: 4px solid #d32f2f;">
            ⚠️ <?= htmlspecialchars($_SESSION['reg_error']) ?>
        </div>
    </div>
    <?php unset($_SESSION['reg_error']); ?>
<?php endif; ?>

<div class="container" style="max-width: 520px; margin-top: 2rem; margin-bottom: 5rem;">

    <!-- Navigation Breadcrumb / Login Toggle -->
    <div style="display: flex; background: var(--gray-light, #f8fafc); border-radius: var(--radius, 6px); padding: 4px; margin-bottom: 1.5rem; border: 1px solid var(--gray-border, #e2e8f0);">
        <a href="login.php?action=login" class="btn" style="flex: 1; text-align: center; border-radius: var(--radius, 6px); text-decoration: none; padding: 0.6rem; font-weight: 600; background: transparent; color: var(--dark, #0f172a);">
            🔐 Sign In
        </a>
        <a href="register.php" class="btn" style="flex: 1; text-align: center; border-radius: var(--radius, 6px); text-decoration: none; padding: 0.6rem; font-weight: 600; background: var(--primary, #1b5e20); color: white;">
            📝 Register Account
        </a>
    </div>

    <!-- Alert Messages -->
    <?php if (!empty($error_message)): ?>
        <div style="background: #f8d7da; color: #721c24; padding: 0.85rem 1.2rem; border-radius: var(--radius, 6px); margin-bottom: 1.5rem; font-weight: 600; font-size: 0.9rem; border-left: 4px solid #d32f2f;">
            ⚠️ <?= htmlspecialchars($error_message) ?>
        </div>
    <?php endif; ?>

    <!-- Registration Card -->
    <div class="card card-body" style="padding: 2rem; border-top: 4px solid var(--accent, #1b5e20); background: white; border-radius: 8px; border: 1px solid #e2e8f0;">
        <h2 style="color: var(--dark, #0f172a); margin-bottom: 0.3rem;">Create Account</h2>
        <p style="color: var(--text-muted, #64748b); font-size: 0.9rem; margin-bottom: 1.5rem;">Join DanFam Market as a Farmer or Produce Buyer.</p>

        <form action="register.php" method="POST">
            <div class="form-group" style="margin-bottom: 1.2rem;">
                <label for="name" style="display: block; font-weight: 600; margin-bottom: 0.4rem; color: var(--dark, #0f172a); font-size: 0.9rem;">Full Name / Business Name *</label>
                <input type="text" id="name" name="name" required placeholder="e.g. Samuel Mwangi" class="form-control" style="width: 100%; padding: 0.75rem; border: 1px solid var(--gray-border, #cbd5e1); border-radius: var(--radius, 6px);" value="<?= isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '' ?>">
            </div>

            <div class="form-group" style="margin-bottom: 1.2rem;">
                <label for="email" style="display: block; font-weight: 600; margin-bottom: 0.4rem; color: var(--dark, #0f172a); font-size: 0.9rem;">Email Address *</label>
                <input type="email" id="email" name="email" required placeholder="e.g. mwangi@farms.co.ke" class="form-control" style="width: 100%; padding: 0.75rem; border: 1px solid var(--gray-border, #cbd5e1); border-radius: var(--radius, 6px);" value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
            </div>

            <div class="form-group" style="margin-bottom: 1.2rem;">
                <label for="password" style="display: block; font-weight: 600; margin-bottom: 0.4rem; color: var(--dark, #0f172a); font-size: 0.9rem;">Password (Min. 6 Characters) *</label>
                <input type="password" id="password" name="password" required minlength="6" placeholder="••••••••" class="form-control" style="width: 100%; padding: 0.75rem; border: 1px solid var(--gray-border, #cbd5e1); border-radius: var(--radius, 6px);">
            </div>

            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label for="role" style="display: block; font-weight: 600; margin-bottom: 0.4rem; color: var(--dark, #0f172a); font-size: 0.9rem;">Account Type / Role *</label>
                <select id="role" name="role" required class="form-control" style="width: 100%; padding: 0.75rem; border: 1px solid var(--gray-border, #cbd5e1); border-radius: var(--radius, 6px); background: white;">
                    <option value="Buyer" <?= (isset($_POST['role']) && $_POST['role'] === 'Buyer') ? 'selected' : '' ?>>🛒 Buyer (Household, Hotel, School, Retailer)</option>
                    <option value="Farmer" <?= (isset($_POST['role']) && $_POST['role'] === 'Farmer') ? 'selected' : '' ?>>👨‍🌾 Smallholder Farmer (Producer / Seller)</option>
                    <?php if ($adminCount < 2): ?>
                        <option value="Administrator" <?= (isset($_POST['role']) && $_POST['role'] === 'Administrator') ? 'selected' : '' ?>>⚙️ System Administrator (<?= 2 - $adminCount ?> slot remaining)</option>
                    <?php endif; ?>
                </select>
            </div>

            <button type="submit" name="register_btn" class="btn btn-accent" style="width: 100%; padding: 0.85rem; font-size: 1rem; font-weight: 700; background: #1b5e20; color: white; border: none; border-radius: 6px; cursor: pointer;">
                ✅ Create Account
            </button>
        </form>

        <div style="text-align: center; margin-top: 1.5rem; font-size: 0.9rem; color: var(--text-muted, #64748b);">
            Already have an account? <a href="login.php?action=login" style="color: var(--primary, #1b5e20); font-weight: 600; text-decoration: none;">Sign In here</a>
        </div>
    </div>

</div>

<?php include 'footer.php'; ?>