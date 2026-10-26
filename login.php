<?php if (isset($_GET['registered']) && $_GET['registered'] == 1): ?>
    <div style="background: #d4edda; color: #155724; padding: 0.85rem 1.2rem; border-radius: var(--radius); margin-bottom: 1.5rem; font-weight: 600; font-size: 0.9rem; border-left: 4px solid #2e7d32;">
        ✅ Account created successfully! Please sign in with your credentials.
    </div>
<?php endif; ?>
<?php

$page_title = "User Authentication";
require_once 'functions.php';

// Redirect if already logged in
if (is_logged_in()) {
    if ($_SESSION['user_role'] === 'Farmer') {
        redirect('farmer_dashboard.php');
    } elseif ($_SESSION['user_role'] === 'Administrator') {
        redirect('admin_dashboard.php');
    } else {
        redirect('catalogue.php');
    }
}

$error_message = '';
$success_message = '';
$action = $_GET['action'] ?? 'login'; // Default view: Login

// --- 1. HANDLE REGISTRATION SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_btn'])) {
    $name     = sanitize_input($_POST['name'] ?? '');
    $email    = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';
    $role     = sanitize_input($_POST['role'] ?? 'Buyer');

    $allowed_roles = ['Buyer', 'Farmer', 'Administrator'];

    if (empty($name) || empty($email) || empty($password)) {
        $error_message = "Please fill in all required registration fields.";
    } elseif (!in_array($role, $allowed_roles)) {
        $error_message = "Invalid account role selected.";
    } elseif (strlen($password) < 6) {
        $error_message = "Password must be at least 6 characters long.";
    } else {
        $reg_result = register_user($pdo, $name, $email, $password, $role);
        if ($reg_result === true) {
            $success_message = "Registration successful! You can now log in below.";
            $action = 'login';
        } else {
            $error_message = $reg_result;
            $action = 'register';
        }
    }
}

// --- 2. HANDLE LOGIN SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_btn'])) {
    $email    = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error_message = "Please enter both your email address and password.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Set session variables
            $_SESSION['user_id']    = $user['id'];
            $_SESSION['user_name']  = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role']  = $user['role'];

            // Role-based route redirection
            if ($user['role'] === 'Farmer') {
                redirect('farmer_dashboard.php');
            } elseif ($user['role'] === 'Administrator') {
                redirect('admin_dashboard.php');
            } else {
                redirect('catalogue.php');
            }
        } else {
            $error_message = "Invalid email address or password combination.";
        }
    }
}

include 'header.php';
?>

<div class="container" style="max-width: 520px; margin-top: 3rem; margin-bottom: 5rem;">
    
    <!-- Tab Toggle Switches -->
    <div style="display: flex; background: var(--gray-light); border-radius: var(--radius); padding: 4px; margin-bottom: 1.5rem; border: 1px solid var(--gray-border);">
        <a href="login.php?action=login" class="btn" style="flex: 1; text-align: center; border-radius: var(--radius); text-decoration: none; padding: 0.6rem; font-weight: 600; <?= $action === 'login' ? 'background: var(--primary); color: white;' : 'background: transparent; color: var(--dark);' ?>">
            🔐 Sign In
        </a>
        <a href="login.php?action=register" class="btn" style="flex: 1; text-align: center; border-radius: var(--radius); text-decoration: none; padding: 0.6rem; font-weight: 600; <?= $action === 'register' ? 'background: var(--primary); color: white;' : 'background: transparent; color: var(--dark);' ?>">
            📝 Register Account
        </a>
    </div>

    <!-- Alert Messages -->
    <?php if (!empty($error_message)): ?>
        <div style="background: #f8d7da; color: #721c24; padding: 0.85rem 1.2rem; border-radius: var(--radius); margin-bottom: 1.5rem; font-weight: 600; font-size: 0.9rem; border-left: 4px solid #d32f2f;">
            ⚠️ <?= htmlspecialchars($error_message) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($success_message)): ?>
        <div style="background: #d4edda; color: #155724; padding: 0.85rem 1.2rem; border-radius: var(--radius); margin-bottom: 1.5rem; font-weight: 600; font-size: 0.9rem; border-left: 4px solid #2e7d32;">
            ✅ <?= htmlspecialchars($success_message) ?>
        </div>
    <?php endif; ?>

    <!-- CARD 1: LOGIN FORM -->
    <?php if ($action === 'login'): ?>
        <div class="card card-body" style="padding: 2rem; border-top: 4px solid var(--primary);">
            <h2 style="color: var(--dark); margin-bottom: 0.3rem;">Welcome Back</h2>
            <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1.5rem;">Access your AgriConnect Kenya portal account.</p>

            <form action="login.php?action=login" method="POST">
                <div class="form-group" style="margin-bottom: 1.2rem;">
                    <label for="email" style="display: block; font-weight: 600; margin-bottom: 0.4rem; color: var(--dark); font-size: 0.9rem;">Email Address</label>
                    <input type="email" id="email" name="email" required placeholder="e.g. farmer@agriconnect.co.ke" class="form-control" style="width: 100%; padding: 0.75rem; border: 1px solid var(--gray-border); border-radius: var(--radius);">
                </div>

                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label for="password" style="display: block; font-weight: 600; margin-bottom: 0.4rem; color: var(--dark); font-size: 0.9rem;">Password</label>
                    <input type="password" id="password" name="password" required placeholder="••••••••" class="form-control" style="width: 100%; padding: 0.75rem; border: 1px solid var(--gray-border); border-radius: var(--radius);">
                </div>

                <button type="submit" name="login_btn" class="btn btn-accent" style="width: 100%; padding: 0.85rem; font-size: 1rem; font-weight: 700;">
                    🚀 Sign In
                </button>
            </form>
        </div>

    <!-- CARD 2: REGISTRATION FORM -->
    <?php else: ?>
        <div class="card card-body" style="padding: 2rem; border-top: 4px solid var(--accent);">
            <h2 style="color: var(--dark); margin-bottom: 0.3rem;">Create Account</h2>
            <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1.5rem;">Join as a Farmer or Produce Buyer across Kenya.</p>

            <form action="login.php?action=register" method="POST">
                <div class="form-group" style="margin-bottom: 1.2rem;">
                    <label for="name" style="display: block; font-weight: 600; margin-bottom: 0.4rem; color: var(--dark); font-size: 0.9rem;">Full Name / Business Name</label>
                    <input type="text" id="name" name="name" required placeholder="e.g. Samuel Mwangi" class="form-control" style="width: 100%; padding: 0.75rem; border: 1px solid var(--gray-border); border-radius: var(--radius);">
                </div>

                <div class="form-group" style="margin-bottom: 1.2rem;">
                    <label for="email" style="display: block; font-weight: 600; margin-bottom: 0.4rem; color: var(--dark); font-size: 0.9rem;">Email Address</label>
                    <input type="email" id="email" name="email" required placeholder="e.g. mwangi@farms.co.ke" class="form-control" style="width: 100%; padding: 0.75rem; border: 1px solid var(--gray-border); border-radius: var(--radius);">
                </div>

                <div class="form-group" style="margin-bottom: 1.2rem;">
                    <label for="password" style="display: block; font-weight: 600; margin-bottom: 0.4rem; color: var(--dark); font-size: 0.9rem;">Password (Min. 6 Characters)</label>
                    <input type="password" id="password" name="password" required minlength="6" placeholder="••••••••" class="form-control" style="width: 100%; padding: 0.75rem; border: 1px solid var(--gray-border); border-radius: var(--radius);">
                </div>

                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label for="role" style="display: block; font-weight: 600; margin-bottom: 0.4rem; color: var(--dark); font-size: 0.9rem;">Account Type / Role</label>
                    <select id="role" name="role" required class="form-control" style="width: 100%; padding: 0.75rem; border: 1px solid var(--gray-border); border-radius: var(--radius);">
                        <option value="Buyer">🛒 Buyer (Household, Hotel, School, Retailer)</option>
                        <option value="Farmer">👨‍🌾 Smallholder Farmer (Producer / Seller)</option>
                        <option value="Administrator">⚙️ System Administrator</option>
                    </select>
                </div>

                <button type="submit" name="register_btn" class="btn btn-accent" style="width: 100%; padding: 0.85rem; font-size: 1rem; font-weight: 700;">
                    ✅ Register Account
                </button>
            </form>
        </div>
    <?php endif; ?>

</div>

<?php include 'footer.php'; ?>