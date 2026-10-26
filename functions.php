<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';

/* ==========================================================================
   1. SECURITY & SANITIZATION HELPERS
   ========================================================================== */

/**
 * Sanitize string input to prevent XSS attacks.
 *
 * @param string $data
 * @return string
 */
function sanitize_input($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Redirect to a specified URL and terminate script execution.
 *
 * @param string $url
 */
function redirect($url) {
    header("Location: " . $url);
    exit;
}

/**
 * Verify CSRF Token to protect against Cross-Site Request Forgery.
 *
 * @param string $token
 * @return bool
 */
function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Generate a new CSRF Token and store it in session.
 *
 * @return string
 */
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/* ==========================================================================
   2. AUTHENTICATION & SESSION GUARDS
   ========================================================================== */

/**
 * Check if a user is currently logged in.
 *
 * @return bool
 */
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

/**
 * Restrict access to logged-in users. Redirects to login if not authenticated.
 */
function require_login() {
    if (!is_logged_in()) {
        redirect('login.php');
    }
}

/**
 * Restrict access to specific user roles (e.g., 'Farmer', 'Administrator', 'Buyer').
 *
 * @param array|string $allowed_roles
 */
function require_role($allowed_roles) {
    require_login();
    $roles = is_array($allowed_roles) ? $allowed_roles : [$allowed_roles];
    
    if (!in_array($_SESSION['user_role'] ?? '', $roles)) {
        redirect('catalogue.php');
    }
}

/**
 * Register a new user into the database.
 *
 * @param PDO $pdo
 * @param string $name
 * @param string $email
 * @param string $password
 * @param string $role
 * @return bool|string True on success, error message on failure
 */
function register_user($pdo, $name, $email, $password, $role) {
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    try {
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->execute([sanitize_input($name), filter_var($email, FILTER_SANITIZE_EMAIL), $hashed_password, $role]);
        return true;
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) { // Duplicate entry
            return "Email address is already registered.";
        }
        return "Registration error: " . $e->getMessage();
    }
}

/* ==========================================================================
   3. ADMINISTRATOR (FULL CRUD) FUNCTIONS
   ========================================================================== */

/**
 * Fetch all system users (Admin view).
 *
 * @param PDO $pdo
 * @return array
 */
function admin_get_all_users($pdo) {
    $stmt = $pdo->query("SELECT id, name, email, role, created_at FROM users ORDER BY id DESC");
    return $stmt->fetchAll();
}

/**
 * Admin updates user account details and role.
 *
 * @param PDO $pdo
 * @param int $id
 * @param string $name
 * @param string $email
 * @param string $role
 * @return bool
 */
function admin_update_user($pdo, $id, $name, $email, $role) {
    $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, role = ? WHERE id = ?");
    return $stmt->execute([sanitize_input($name), filter_var($email, FILTER_SANITIZE_EMAIL), sanitize_input($role), intval($id)]);
}

/**
 * Admin deletes a user from the system.
 *
 * @param PDO $pdo
 * @param int $id
 * @return bool
 */
function admin_delete_user($pdo, $id) {
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    return $stmt->execute([intval($id)]);
}

/**
 * Admin updates the approval status of any produce listing.
 *
 * @param PDO $pdo
 * @param int $produce_id
 * @param string $status ('Approved', 'Pending', 'Rejected')
 * @return bool
 */
function admin_update_produce_status($pdo, $produce_id, $status) {
    $stmt = $pdo->prepare("UPDATE produce SET status = ? WHERE id = ?");
    return $stmt->execute([sanitize_input($status), intval($produce_id)]);
}

/**
 * Admin deletes any produce listing.
 *
 * @param PDO $pdo
 * @param int $produce_id
 * @return bool
 */
function admin_delete_produce($pdo, $produce_id) {
    $stmt = $pdo->prepare("DELETE FROM produce WHERE id = ?");
    return $stmt->execute([intval($produce_id)]);
}

/* ==========================================================================
   4. FARMER PRODUCE & RESPONSIBILITY FUNCTIONS
   ========================================================================== */

/**
 * Fetch all produce items belonging to a specific farmer.
 *
 * @param PDO $pdo
 * @param int $farmer_id
 * @return array
 */
function farmer_get_produce($pdo, $farmer_id) {
    $stmt = $pdo->prepare("SELECT p.*, c.name AS category_name FROM produce p JOIN categories c ON p.category_id = c.id WHERE p.farmer_id = ? ORDER BY p.id DESC");
    $stmt->execute([intval($farmer_id)]);
    return $stmt->fetchAll();
}

/**
 * Farmer adds a new agricultural produce listing.
 *
 * @param PDO $pdo
 * @param int $farmer_id
 * @param array $data
 * @param string $photo_path
 * @return bool
 */
function farmer_add_produce($pdo, $farmer_id, $data, $photo_path) {
    $stmt = $pdo->prepare("INSERT INTO produce (farmer_id, category_id, name, description, price, unit, quantity, location, image, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')");
    return $stmt->execute([
        intval($farmer_id),
        intval($data['category_id']),
        sanitize_input($data['name']),
        sanitize_input($data['description']),
        floatval($data['price']),
        sanitize_input($data['unit']),
        floatval($data['quantity']),
        sanitize_input($data['location']),
        $photo_path
    ]);
}

/**
 * Farmer updates their own produce listing.
 *
 * @param PDO $pdo
 * @param int $produce_id
 * @param int $farmer_id
 * @param array $data
 * @return bool
 */
function farmer_update_produce($pdo, $produce_id, $farmer_id, $data) {
    $stmt = $pdo->prepare("UPDATE produce SET category_id = ?, name = ?, description = ?, price = ?, unit = ?, quantity = ?, location = ? WHERE id = ? AND farmer_id = ?");
    return $stmt->execute([
        intval($data['category_id']),
        sanitize_input($data['name']),
        sanitize_input($data['description']),
        floatval($data['price']),
        sanitize_input($data['unit']),
        floatval($data['quantity']),
        sanitize_input($data['location']),
        intval($produce_id),
        intval($farmer_id)
    ]);
}

/**
 * Farmer deletes their own produce listing.
 *
 * @param PDO $pdo
 * @param int $produce_id
 * @param int $farmer_id
 * @return bool
 */
function farmer_delete_produce($pdo, $produce_id, $farmer_id) {
    $stmt = $pdo->prepare("DELETE FROM produce WHERE id = ? AND farmer_id = ?");
    return $stmt->execute([intval($produce_id), intval($farmer_id)]);
}

/* ==========================================================================
   5. GENERAL PRODUCE MANAGEMENT FUNCTIONS
   ========================================================================== */

/**
 * Fetch all approved produce items for the public catalogue with optional search term.
 *
 * @param PDO $pdo
 * @param string $search_term
 * @return array
 */
function get_approved_produce($pdo, $search_term = '') {
    if (!empty($search_term)) {
        $stmt = $pdo->prepare("SELECT p.*, c.name AS category_name, u.name AS farmer_name 
                               FROM produce p 
                               JOIN categories c ON p.category_id = c.id 
                               JOIN users u ON p.farmer_id = u.id 
                               WHERE p.status = 'Approved' 
                               AND (p.name LIKE ? OR p.location LIKE ? OR c.name LIKE ?) 
                               ORDER BY p.id DESC");
        $term = "%{$search_term}%";
        $stmt->execute([$term, $term, $term]);
    } else {
        $stmt = $pdo->query("SELECT p.*, c.name AS category_name, u.name AS farmer_name 
                             FROM produce p 
                             JOIN categories c ON p.category_id = c.id 
                             JOIN users u ON p.farmer_id = u.id 
                             WHERE p.status = 'Approved' 
                             ORDER BY p.id DESC");
    }
    return $stmt->fetchAll();
}

/**
 * Get detailed information for a specific produce item by ID.
 *
 * @param PDO $pdo
 * @param int $id
 * @return array|false
 */
function get_produce_by_id($pdo, $id) {
    $stmt = $pdo->prepare("SELECT p.*, c.name AS category_name, u.name AS farmer_name, u.email AS farmer_email 
                           FROM produce p 
                           JOIN categories c ON p.category_id = c.id 
                           JOIN users u ON p.farmer_id = u.id 
                           WHERE p.id = ?");
    $stmt->execute([intval($id)]);
    return $stmt->fetch();
}

/**
 * Upload a produce photo safely.
 *
 * @param array $file_array
 * @param string $target_dir
 * @return string Path to saved image or default placeholder
 */
function upload_produce_photo($file_array, $target_dir = 'uploads/') {
    if (isset($file_array) && $file_array['error'] === UPLOAD_ERR_OK) {
        $extension = strtolower(pathinfo($file_array['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];

        if (in_array($extension, $allowed)) {
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0755, true);
            }
            $filename = uniqid('prod_', true) . '.' . $extension;
            $destination = $target_dir . $filename;
            
            if (move_uploaded_file($file_array['tmp_name'], $destination)) {
                return $destination;
            }
        }
    }
    return 'default_produce.jpg';
}

/* ==========================================================================
   6. ORDER & BUYER FUNCTIONS
   ========================================================================== */

/**
 * Fetch all items belonging to a specific order ID.
 *
 * @param PDO $pdo
 * @param int $order_id
 * @return array
 */
function get_order_items($pdo, $order_id) {
    $stmt = $pdo->prepare("SELECT oi.*, p.name AS produce_name, p.unit 
                           FROM order_items oi 
                           JOIN produce p ON oi.produce_id = p.id 
                           WHERE oi.order_id = ?");
    $stmt->execute([intval($order_id)]);
    return $stmt->fetchAll();
}

/**
 * Fetch orders placed by a specific buyer.
 *
 * @param PDO $pdo
 * @param int $buyer_id
 * @return array
 */
function buyer_get_orders($pdo, $buyer_id) {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE buyer_id = ? ORDER BY id DESC");
    $stmt->execute([intval($buyer_id)]);
    return $stmt->fetchAll();
}

/**
 * Render a badge span element for order or produce statuses with consistent colors.
 *
 * @param string $status
 * @return string HTML span tag
 */
function render_status_badge($status) {
    $status_clean = strtolower(sanitize_input($status));
    return sprintf(
        '<span class="badge badge-%s">%s</span>',
        $status_clean,
        ucfirst($status_clean)
    );
}

/**
 * Format currency values in Kenyan Shillings (KSh).
 *
 * @param float $amount
 * @return string
 */
function format_currency($amount) {
    return 'KSh ' . number_format($amount, 2);
}
?>