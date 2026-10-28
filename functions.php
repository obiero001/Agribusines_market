<?php
declare(strict_types=1);

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
    
    if (!in_array($_SESSION['user_role'] ?? '', $roles, true)) {
        redirect('catalogue.php');
    }
}

/**
 * Register a new user into the database (`danfam_market`) and auto-approve them.
 *
 * @param PDO $pdo
 * @param string $name
 * @param string $email
 * @param string $password
 * @param string $role
 * @return bool|string True on success, error message on failure
 */
function register_user($pdo, $name, $email, $password, $role) {
    try {
        $checkStmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
        $checkStmt->execute([$email]);
        if ($checkStmt->fetch()) {
            return "Email address is already registered.";
        }

        $username = explode('@', $email)[0] . rand(100, 999);
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Auto-approve all newly registered roles (including Farmers) so they can log in immediately
        $is_approved = 1;

        $stmt = $pdo->prepare("
            INSERT INTO users (username, email, password_hash, full_name, role, is_approved) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $username,
            filter_var($email, FILTER_SANITIZE_EMAIL),
            $hashed_password,
            sanitize_input($name),
            $role,
            $is_approved
        ]);

        return true;
    } catch (PDOException $e) {
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
    $stmt = $pdo->query("SELECT user_id AS id, full_name AS name, email, role, is_approved, created_at FROM users ORDER BY user_id DESC");
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
    $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, role = ? WHERE user_id = ?");
    return $stmt->execute([sanitize_input($name), filter_var($email, FILTER_SANITIZE_EMAIL), sanitize_input($role), (int)$id]);
}

/**
 * Admin deletes a user from the system.
 *
 * @param PDO $pdo
 * @param int $id
 * @return bool
 */
function admin_delete_user($pdo, $id) {
    $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
    return $stmt->execute([(int)$id]);
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
    $stmt = $pdo->prepare("UPDATE produce SET status = ? WHERE produce_id = ?");
    return $stmt->execute([sanitize_input($status), (int)$produce_id]);
}

/**
 * Admin deletes any produce listing.
 *
 * @param PDO $pdo
 * @param int $produce_id
 * @return bool
 */
function admin_delete_produce($pdo, $produce_id) {
    $stmt = $pdo->prepare("DELETE FROM produce WHERE produce_id = ?");
    return $stmt->execute([(int)$produce_id]);
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
    $stmt = $pdo->prepare("
        SELECT p.produce_id AS id, p.produce_name AS name, p.description, p.price_per_unit AS price, 
               p.unit, p.quantity_available AS quantity, p.location, p.photo_url AS image, p.status, 
               c.category_name AS category_name 
        FROM produce p 
        JOIN categories c ON p.category_id = c.category_id 
        WHERE p.farmer_id = ? 
        ORDER BY p.produce_id DESC
    ");
    $stmt->execute([(int)$farmer_id]);
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
    $stmt = $pdo->prepare("
        INSERT INTO produce (farmer_id, category_id, produce_name, description, price_per_unit, unit, quantity_available, location, photo_url, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')
    ");
    return $stmt->execute([
        (int)$farmer_id,
        (int)$data['category_id'],
        sanitize_input($data['name']),
        sanitize_input($data['description']),
        (float)$data['price'],
        sanitize_input($data['unit']),
        (float)$data['quantity'],
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
    $stmt = $pdo->prepare("
        UPDATE produce 
        SET category_id = ?, produce_name = ?, description = ?, price_per_unit = ?, unit = ?, quantity_available = ?, location = ? 
        WHERE produce_id = ? AND farmer_id = ?
    ");
    return $stmt->execute([
        (int)$data['category_id'],
        sanitize_input($data['name']),
        sanitize_input($data['description']),
        (float)$data['price'],
        sanitize_input($data['unit']),
        (float)$data['quantity'],
        sanitize_input($data['location']),
        (int)$produce_id,
        (int)$farmer_id
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
    $stmt = $pdo->prepare("DELETE FROM produce WHERE produce_id = ? AND farmer_id = ?");
    return $stmt->execute([(int)$produce_id, (int)$farmer_id]);
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
        $stmt = $pdo->prepare("
            SELECT p.produce_id AS id, p.produce_name AS name, p.description, p.price_per_unit AS price, 
                   p.unit, p.quantity_available AS quantity, p.location, p.photo_url AS image, 
                   c.category_name AS category_name, u.full_name AS farmer_name 
            FROM produce p 
            JOIN categories c ON p.category_id = c.category_id 
            JOIN users u ON p.farmer_id = u.user_id 
            WHERE p.status = 'Approved' 
            AND (p.produce_name LIKE ? OR p.location LIKE ? OR c.category_name LIKE ?) 
            ORDER BY p.produce_id DESC
        ");
        $term = "%{$search_term}%";
        $stmt->execute([$term, $term, $term]);
    } else {
        $stmt = $pdo->query("
            SELECT p.produce_id AS id, p.produce_name AS name, p.description, p.price_per_unit AS price, 
                   p.unit, p.quantity_available AS quantity, p.location, p.photo_url AS image, 
                   c.category_name AS category_name, u.full_name AS farmer_name 
            FROM produce p 
            JOIN categories c ON p.category_id = c.category_id 
            JOIN users u ON p.farmer_id = u.user_id 
            WHERE p.status = 'Approved' 
            ORDER BY p.produce_id DESC
        ");
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
    $stmt = $pdo->prepare("
        SELECT p.produce_id AS id, p.produce_name AS name, p.description, p.price_per_unit AS price, 
               p.unit, p.quantity_available AS quantity, p.location, p.photo_url AS image, p.status, 
               c.category_name AS category_name, u.full_name AS farmer_name, u.email AS farmer_email 
        FROM produce p 
        JOIN categories c ON p.category_id = c.category_id 
        JOIN users u ON p.farmer_id = u.user_id 
        WHERE p.produce_id = ?
    ");
    $stmt->execute([(int)$id]);
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

        if (in_array($extension, $allowed, true)) {
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
    $stmt = $pdo->prepare("
        SELECT oi.*, p.produce_name AS name, p.unit 
        FROM order_items oi 
        JOIN produce p ON oi.produce_id = p.produce_id 
        WHERE oi.order_id = ?
    ");
    $stmt->execute([(int)$order_id]);
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
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE buyer_id = ? ORDER BY order_id DESC");
    $stmt->execute([(int)$buyer_id]);
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
    return 'KSh ' . number_format((float)$amount, 2);
}
?>