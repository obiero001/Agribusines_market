<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';
require_once 'functions.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('register.php');
}

// 1. Sanitize and Extract Input Data
$name     = sanitize_input($_POST['name'] ?? '');
$email    = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
$password = $_POST['password'] ?? '';
$role     = sanitize_input($_POST['role'] ?? 'Buyer');

$allowed_roles = ['Buyer', 'Farmer', 'Administrator'];

// 2. Input Validation
if (empty($name) || empty($email) || empty($password)) {
    $_SESSION['reg_error'] = "All required fields must be filled out.";
    redirect('register.php');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['reg_error'] = "Please provide a valid email address.";
    redirect('register.php');
}

if (strlen($password) < 6) {
    $_SESSION['reg_error'] = "Password must be at least 6 characters long.";
    redirect('register.php');
}

if (!in_array($role, $allowed_roles)) {
    $_SESSION['reg_error'] = "Invalid account role selected.";
    redirect('register.php');
}

// 3. Check for Duplicate Email Entry
try {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $_SESSION['reg_error'] = "An account with this email address already exists.";
        redirect('register.php');
    }

    // 4. Hash Password & Insert User
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $insert_stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
    $insert_stmt->execute([$name, $email, $hashed_password, $role]);
    
    $new_user_id = $pdo->lastInsertId();

    // 5. Automatic Session Authentication
    $_SESSION['user_id']    = $new_user_id;
    $_SESSION['user_name']  = $name;
    $_SESSION['user_email'] = $email;
    $_SESSION['user_role']  = $role;

    // 6. Role-Based Destination Routing
    if ($role === 'Farmer') {
        redirect('farmer_dashboard.php');
    } elseif ($role === 'Administrator') {
        redirect('admin_dashboard.php');
    } else {
        // If buyer came from shopping cart checkout, redirect to cart/checkout
        if (isset($_SESSION['cart_pending']) && $_SESSION['cart_pending'] === true) {
            unset($_SESSION['cart_pending']);
            redirect('cart.php');
        }
        redirect('catalogue.php');
    }

} catch (PDOException $e) {
    error_log("Registration Error: " . $e->getMessage());
    $_SESSION['reg_error'] = "A system error occurred during registration. Please try again.";
    redirect('register.php');
}
?>