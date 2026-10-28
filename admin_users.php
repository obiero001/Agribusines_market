<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';
require_once 'functions.php';

// Ensure only Administrators can access this script
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'Administrator') {
    $_SESSION['error'] = "Unauthorized access.";
    redirect('login.php');
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'create':
            // Admin creates a new user sure
            $name     = sanitize_input($_POST['name']);
            $email    = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
            $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
            $role     = sanitize_input($_POST['role']);

            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $email, $password, $role]);
            
            $_SESSION['success'] = "User created successfully.";
            break;

        case 'update':
            // Admin updates an existing user okay
            $id       = intval($_POST['id']);
            $name     = sanitize_input($_POST['name']);
            $email    = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
            $role     = sanitize_input($_POST['role']);

            if (!empty($_POST['password'])) {
                $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, password = ?, role = ? WHERE id = ?");
                $stmt->execute([$name, $email, $password, $role, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, role = ? WHERE id = ?");
                $stmt->execute([$name, $email, $role, $id]);
            }

            $_SESSION['success'] = "User updated successfully.";
            break;

        case 'delete':
            // Admin deletes a user sawa
            $id = intval($_GET['id']);
            // Prevent admin from deleting themselves
            if ($id === $_SESSION['user_id']) {
                $_SESSION['error'] = "You cannot delete your own active admin account.";
                break;
            }

            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$id]);

            $_SESSION['success'] = "User deleted successfully.";
            break;

        default:
            break;
    }
} catch (PDOException $e) {
    error_log("Admin Action Error: " . $e->getMessage());
    $_SESSION['error'] = "A database error occurred.";
}

redirect('admin_dashboard.php');
?>