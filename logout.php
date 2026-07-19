<?php
session_start();

// Clear all session variables
$_SESSION = array();

// Destroy the session cookie (if using cookie-based sessions)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session itself
session_destroy();

// Redirect to login page
header("Location: login.php");
exit();
?>
