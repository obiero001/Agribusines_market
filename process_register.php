<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';
require_once 'functions.php';

// Only allow POST requests sawa
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('register.php');
}

// 1. Sanitize and Extract Input Data
$name     = sanitize_input($_POST['full_name'] ?? '');
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

if (!in_array($role, $allowed_roles, true)) {
    $_SESSION['reg_error'] = "Invalid account role selected.";
    redirect('register.php');
}

// 3. Check for Duplicate Email Entry (Using correct user_id column)
try {
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $_SESSION['reg_error'] = "An account with this email address already exists.";
        redirect('register.php');
    }

    // Generate clean username from email prefix
    $username = explode('@', $email)[0] . rand(100, 999);

    // Farmers require Admin approval (is_approved = 0), Buyers/Admins are approved (1)
    $is_approved = ($role === 'Farmer') ? 0 : 1;

    // 4. Hash Password & Insert User (Using correct full_name, password_hash, and is_approved columns)
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $insert_stmt = $pdo->prepare("
        INSERT INTO users (username, email, password_hash, full_name, role, is_approved) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $insert_stmt->execute([$username, $email, $hashed_password, $name, $role, $is_approved]);
    
    $new_user_id = (int)$pdo->lastInsertId();

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