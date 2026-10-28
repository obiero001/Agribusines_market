<?php
declare(strict_types=1);

// Enable error reporting to diagnose any hidden issues on screen
ini_set('display_errors', '1');
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'functions.php';

// Redirect if already logged in
if (is_logged_in()) {
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
$action = trim((string) ($_GET['action'] ?? 'login'));

// --- 1. HANDLE REGISTRATION SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_btn'])) {
    $name     = sanitize_input($_POST['name'] ?? '');
    $email    = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';
    $role     = sanitize_input($_POST['role'] ?? 'Buyer');

    $allowed_roles = ['Buyer', 'Farmer', 'Administrator'];

    if (empty($name) || empty($email) || empty($password)) {
        $error_message = "Please fill in all required registration fields.";
        $action = 'register';
    } elseif (!in_array($role, $allowed_roles, true)) {
        $error_message = "Invalid account role selected.";
        $action = 'register';
    } elseif (strlen($password) < 6) {
        $error_message = "Password must be at least 6 characters long.";
        $action = 'register';
    } else {
        $reg_result = register_user($pdo, $name, $email, $password, $role);
        if ($reg_result === true) {
            redirect('login.php?registered=1');
        } else {
            $error_message = (string) $reg_result;
            $action = 'register';
        }
    }
}

// --- 2. HANDLE LOGIN SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_btn'])) {
    $email    = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';
    $action   = 'login';

    if (empty($email) || empty($password)) {
        $error_message = "Please enter both your email address and password.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user) {
                $error_message = "No account found with that email address.";
            } elseif (!password_verify($password, $user['password_hash'])) {
                $error_message = "Incorrect password combination.";
            } else {
                // Set session variables matching danfam_market schema
                $_SESSION['user_id']    = (int) $user['user_id'];
                $_SESSION['user_name']  = (string) $user['full_name'];
                $_SESSION['user_email'] = (string) $user['email'];
                $_SESSION['user_role']  = (string) $user['role'];

                // Role-based redirection
                if ($user['role'] === 'Farmer') {
                    redirect('farmer_dashboard.php');
                } elseif ($user['role'] === 'Administrator') {
                    redirect('admin_dashboard.php');
                } else {
                    redirect('catalogue.php');
                }
            }
        } catch (PDOException $e) {
            $error_message = "Database error: " . $e->getMessage();
        }
    }
}

$page_title = "User Authentication";
include 'header.php';
?>

<div class="container" style="max-width: 520px; margin-top: 3rem; margin-bottom: 5rem;">

    <!-- Registration Success Banner -->
    <?php if (isset($_GET['registered']) && $_GET['registered'] === '1'): ?>
        <div style="background: #d4edda; color: #155724; padding: 0.85rem 1.2rem; border-radius: 6px; margin-bottom: 1.5rem; font-weight: 600; font-size: 0.9rem; border-left: 4px solid #2e7d32;">
            ✅ Account created successfully! Please sign in with your credentials below.
        </div>
    <?php endif; ?>
    
    <!-- Tab Toggle Switches -->
    <div style="display: flex; background: #f8fafc; border-radius: 6px; padding: 4px; margin-bottom: 1.5rem; border: 1px solid #e2e8f0;">
        <a href="login.php?action=login" class="btn" style="flex: 1; text-align: center; border-radius: 6px; text-decoration: none; padding: 0.6rem; font-weight: 600; <?= $action === 'login' ? 'background: #1b5e20; color: white;' : 'background: transparent; color: #0f172a;' ?>">
            🔐 Sign In
        </a>
        <a href="login.php?action=register" class="btn" style="flex: 1; text-align: center; border-radius: 6px; text-decoration: none; padding: 0.6rem; font-weight: 600; <?= $action === 'register' ? 'background: #1b5e20; color: white;' : 'background: transparent; color: #0f172a;' ?>">
            Register Account
        </a>
    </div>

    <!-- Alert Messages -->
    <?php if (!empty($error_message)): ?>
        <div style="background: #f8d7da; color: #721c24; padding: 0.85rem 1.2rem; border-radius: 6px; margin-bottom: 1.5rem; font-weight: 600; font-size: 0.9rem; border-left: 4px solid #d32f2f;">
            ⚠️ <?= htmlspecialchars($error_message) ?>
        </div>
    <?php endif; ?>

    <!-- CARD 1: LOGIN FORM -->
    <?php if ($action === 'login'): ?>
        <div class="card card-body" style="padding: 2rem; border-top: 4px solid #1b5e20; background: white; border-radius: 8px; border: 1px solid #e2e8f0;">
            <h2 style="color: #0f172a; margin-bottom: 0.3rem;">Welcome Back</h2>
            <p style="color: #64748b; font-size: 0.9rem; margin-bottom: 1.5rem;">Access your DanFam Market portal account.</p>

            <form action="login.php?action=login" method="POST">
                <div class="form-group" style="margin-bottom: 1.2rem;">
                    <label for="email" style="display: block; font-weight: 600; margin-bottom: 0.4rem; color: #0f172a; font-size: 0.9rem;">Email Address</label>
                    <input type="email" id="email" name="email" required placeholder="e.g. farmer@danfam.co.ke" class="form-control" style="width: 100%; padding: 0.75rem; border: 1px solid #cbd5e1; border-radius: 6px;" value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
                </div>

                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label for="password" style="display: block; font-weight: 600; margin-bottom: 0.4rem; color: #0f172a; font-size: 0.9rem;">Password</label>
                    <input type="password" id="password" name="password" required placeholder="••••••••" class="form-control" style="width: 100%; padding: 0.75rem; border: 1px solid #cbd5e1; border-radius: 6px;">
                </div>

                <button type="submit" name="login_btn" class="btn btn-accent" style="width: 100%; padding: 0.85rem; font-size: 1rem; font-weight: 700; background: #ff9800; color: white; border: none; border-radius: 6px; cursor: pointer;">
                    🚀 Sign In
                </button>
            </form>
        </div>

    <!-- CARD 2: REGISTRATION FORM -->
    <?php else: ?>
        <div class="card card-body" style="padding: 2rem; border-top: 4px solid #ff9800; background: white; border-radius: 8px; border: 1px solid #e2e8f0;">
            <h2 style="color: #0f172a; margin-bottom: 0.3rem;">Create Account</h2>
            <p style="color: #64748b; font-size: 0.9rem; margin-bottom: 1.5rem;">Join as a Farmer or Produce Buyer across Kenya.</p>

            <form action="login.php?action=register" method="POST">
                <div class="form-group" style="margin-bottom: 1.2rem;">
                    <label for="name" style="display: block; font-weight: 600; margin-bottom: 0.4rem; color: #0f172a; font-size: 0.9rem;">Full Name / Business Name</label>
                    <input type="text" id="name" name="name" required placeholder="e.g. Samuel Mwangi" class="form-control" style="width: 100%; padding: 0.75rem; border: 1px solid #cbd5e1; border-radius: 6px;" value="<?= isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '' ?>">
                </div>

                <div class="form-group" style="margin-bottom: 1.2rem;">
                    <label for="email" style="display: block; font-weight: 600; margin-bottom: 0.4rem; color: #0f172a; font-size: 0.9rem;">Email Address</label>
                    <input type="email" id="email" name="email" required placeholder="e.g. mwangi@farms.co.ke" class="form-control" style="width: 100%; padding: 0.75rem; border: 1px solid #cbd5e1; border-radius: 6px;" value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
                </div>

                <div class="form-group" style="margin-bottom: 1.2rem;">
                    <label for="password" style="display: block; font-weight: 600; margin-bottom: 0.4rem; color: #0f172a; font-size: 0.9rem;">Password (Min. 6 Characters)</label>
                    <input type="password" id="password" name="password" required minlength="6" placeholder="••••••••" class="form-control" style="width: 100%; padding: 0.75rem; border: 1px solid #cbd5e1; border-radius: 6px;">
                </div>

                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label for="role" style="display: block; font-weight: 600; margin-bottom: 0.4rem; color: #0f172a; font-size: 0.9rem;">Account Type / Role</label>
                    <select id="role" name="role" required class="form-control" style="width: 100%; padding: 0.75rem; border: 1px solid #cbd5e1; border-radius: 6px; background: white;">
                        <option value="Buyer">🛒 Buyer (Household, Hotel, School, Retailer)</option>
                        <option value="Farmer">👨‍🌾 Smallholder Farmer (Producer / Seller)</option>
                        <option value="Administrator">⚙️ System Administrator</option>
                    </select>
                </div>

                <button type="submit" name="register_btn" class="btn btn-accent" style="width: 100%; padding: 0.85rem; font-size: 1rem; font-weight: 700; background: #1b5e20; color: white; border: none; border-radius: 6px; cursor: pointer;">
                    ✅ Register Account
                </button>
            </form>
        </div>
    <?php endif; ?>

</div>

<?php include 'footer.php'; ?>