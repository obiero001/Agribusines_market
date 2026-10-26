<?php

session_start();

// 1. Unset all active session variables
$_SESSION = array();

// 2. Invalidate and clear the session cookie if present
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// 3. Destroy the session completely on the server
session_destroy();

// 4. Redirect user back to login portal with an appropriate success message
header('Location: login.php?msg=logged_out');
exit;
?>