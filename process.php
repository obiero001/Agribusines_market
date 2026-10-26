<?php
declare(strict_types=1);

// Enable errors in server logs, hide from browser output for security
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

require_once  'config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

//  Redirect and terminate execution.
 
function redirectTo(string $url): never
{
    header('Location: ' . $url);
    exit;
}

//  Stop the request with an HTTP status code.
 
function stopRequest(string $message, int $status = 400): never
{
    http_response_code($status);
    exit($message);
}

// 1. AUTHENTICATION CHECK
if (empty($_SESSION['user_id'])) {
    redirectTo('login.php?error=unauthorized');
}

$userId = filter_var($_SESSION['user_id'], FILTER_VALIDATE_INT);

if (!$userId || $userId < 1) {
    $_SESSION = [];
    session_destroy();
    redirectTo('login.php?error=unauthorized');
}

$userId = (int) $userId;
$userRole = $_SESSION['user_role'] ?? '';

$allowedRoles = ['Administrator', 'Farmer', 'Buyer'];

if (!in_array($userRole, $allowedRoles, true)) {
    stopRequest('Access denied: invalid user role.', 403);
}

// 2. HTTP METHOD CHECK (Accept POST requests only)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    stopRequest('Method not allowed. Submit an appropriate form.', 405);
}

//  Validate a positive integer ID or quantity.
 
function positiveInt(mixed $value): int
{
    $result = filter_var($value, FILTER_VALIDATE_INT);
    if ($result === false || $result < 1) {
        stopRequest('Invalid ID or quantity parameter.');
    }
    return (int) $result;
}

//  Validate a monetary amount.
 
function validMoney(mixed $value): string
{
    if (!is_scalar($value) || !is_numeric((string) $value)) {
        stopRequest('Invalid price format.');
    }
    $number = (float) $value;
    if (!is_finite($number) || $number < 0 || $number > 999999999) {
        stopRequest('Price is outside permitted range.');
    }
    return number_format($number, 2, '.', '');
}

// GET ACTION VALUE FROM POST
$action = trim((string) ($_POST['action'] ?? ''));

if ($action === '') {
    redirectTo('index.html?error=missing_action');
}

try {
    switch ($action) {

        case 'add_produce':
            if (!in_array($userRole, ['Farmer', 'Administrator'], true)) {
                stopRequest('Only farmers or administrators can post produce listings.', 403);
            }

            $categoryId = positiveInt($_POST['category_id'] ?? null);
            $name = trim((string) ($_POST['name'] ?? ''));
            $description = trim((string) ($_POST['description'] ?? ''));
            $location = trim((string) ($_POST['location'] ?? ''));
            $unit = trim((string) ($_POST['unit'] ?? 'kg'));

            if ($name === '' || $location === '' || mb_strlen($name) > 150) {
                redirectTo('form.php?error=invalid_fields');
            }

            $price = validMoney($_POST['price'] ?? null);
            $quantity = (float) ($_POST['quantity'] ?? 0);

            if ($quantity <= 0) {
                redirectTo('form.php?error=invalid_quantity');
            }

            // STICKILY REQUIRE AN IMAGE UPLOAD FOR NEW PRODUCE
            if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
                redirectTo('form.php?error=image_required');
            }

            $fileTmpPath = $_FILES['photo']['tmp_name'];
            $fileName = $_FILES['photo']['name'];
            $fileSize = $_FILES['photo']['size'];
            
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'avif'];

            if (!in_array($fileExtension, $allowedExtensions, true) || $fileSize > (5 * 1024 * 1024)) {
                redirectTo('form.php?error=invalid_image_format_or_size');
            }

            $newFileName = 'produce_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $fileExtension;
            $uploadFileDir = __DIR__ . '/uploads/';

            if (!is_dir($uploadFileDir)) {
                mkdir($uploadFileDir, 0755, true);
            }

            if (!move_uploaded_file($fileTmpPath, $uploadFileDir . $newFileName)) {
                redirectTo('form.php?error=image_upload_failed');
            }

            $photo = $newFileName; // Uses the exact uploaded image filename

            $status = ($userRole === 'Administrator') ? 'Approved' : 'Pending';

            $stmt = $pdo->prepare(
                "INSERT INTO produce (farmer_id, category_id, name, description, price, quantity, unit, location, photo, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([$userId, $categoryId, $name, $description, $price, $quantity, $unit, $location, $photo, $status]);

            $target = ($userRole === 'Administrator') ? 'admin_dashboard.php?success=produce_posted' : 'farmer_dashboard.php?success=produce_posted';
            redirectTo($target);
            break;

            // HANDLE IMAGE UPLOAD SECURELY
            $photo = '';

            if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                $fileTmpPath = $_FILES['photo']['tmp_name'];
                $fileName = $_FILES['photo']['name'];
                $fileSize = $_FILES['photo']['size'];
                
                $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'avif'];

                if (in_array($fileExtension, $allowedExtensions, true) && $fileSize <= (5 * 1024 * 1024)) {
                    $newFileName = 'produce_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $fileExtension;
                    $uploadFileDir = 'uploads';

                    if (!is_dir($uploadFileDir)) {
                        mkdir($uploadFileDir, 0755, true);
                    }

                    if (move_uploaded_file($fileTmpPath, $uploadFileDir . $newFileName)) {
                        $photo = $newFileName;
                    }
                }
            }

            $status = ($userRole === 'Administrator') ? 'Approved' : 'Pending';

            $stmt = $pdo->prepare(
                "INSERT INTO produce (farmer_id, category_id, name, description, price, quantity, unit, location, photo, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([$userId, $categoryId, $name, $description, $price, $quantity, $unit, $location, $photo, $status]);

            $target = ($userRole === 'Administrator') ? 'admin_dashboard.php?success=produce_posted' : 'farmer_dashboard.php?success=produce_posted';
            redirectTo($target);
            break;

        // 2. EDIT PRODUCE (Farmer & Administrator)
        case 'edit_produce':
            $produceId = positiveInt($_POST['id'] ?? null);
            $categoryId = positiveInt($_POST['category_id'] ?? null);
            $name = trim((string) ($_POST['name'] ?? ''));
            $description = trim((string) ($_POST['description'] ?? ''));
            $location = trim((string) ($_POST['location'] ?? ''));
            $unit = trim((string) ($_POST['unit'] ?? 'kg'));

            if ($name === '' || $location === '' || mb_strlen($name) > 150) {
                redirectTo('form.php?id=' . $produceId . '&error=invalid_fields');
            }

            $price = validMoney($_POST['price'] ?? null);
            $quantity = (float) ($_POST['quantity'] ?? 0);

            if ($quantity <= 0) {
                redirectTo('form.php?id=' . $produceId . '&error=invalid_quantity');
            }

            // Optional new photo upload handling during edit
            $photoSql = "";
            $params = [$categoryId, $name, $description, $price, $quantity, $unit, $location];

            if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                $fileTmpPath = $_FILES['photo']['tmp_name'];
                $fileName = $_FILES['photo']['name'];
                $fileSize = $_FILES['photo']['size'];
                $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'avif'];

                if (in_array($fileExtension, $allowedExtensions, true) && $fileSize <= (5 * 1024 * 1024)) {
                    $newFileName = 'produce_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $fileExtension;
                    $uploadFileDir = __DIR__ . '/uploads/';

                    if (!is_dir($uploadFileDir)) {
                        mkdir($uploadFileDir, 0755, true);
                    }

                    if (move_uploaded_file($fileTmpPath, $uploadFileDir . $newFileName)) {
                        $photoSql = ", photo = ?";
                        $params[] = $newFileName;
                    }
                }
            }

            if ($userRole === 'Administrator') {
                $params[] = $produceId;
                $stmt = $pdo->prepare(
                    "UPDATE produce SET category_id = ?, name = ?, description = ?, price = ?, quantity = ?, unit = ?, location = ?" . $photoSql . " WHERE id = ?"
                );
                $stmt->execute($params);
                redirectTo('admin_dashboard.php?success=produce_updated');
            } elseif ($userRole === 'Farmer') {
                $params[] = $produceId;
                $params[] = $userId;
                $stmt = $pdo->prepare(
                    "UPDATE produce SET category_id = ?, name = ?, description = ?, price = ?, quantity = ?, unit = ?, location = ?, status = 'Pending'" . $photoSql . " WHERE id = ? AND farmer_id = ?"
                );
                $stmt->execute($params);
                redirectTo('farmer_dashboard.php?success=produce_updated');
            } else {
                stopRequest('Unauthorized action.', 403);
            }
            break;

        default:
            redirectTo('index.php?error=invalid_action');
    }
} catch (Throwable $e) {
    error_log('[DanFam Market process.php Error] ' . $e->getMessage());
    redirectTo('form.php?error=server_error');
}