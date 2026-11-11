<?php
// logout.php
// IMPORTANT: Save this file as UTF-8 without BOM.
// Do not put spaces or blank lines before <?php.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Clear all session variables
$_SESSION = [];

// Delete session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// Destroy session
session_destroy();

// Redirect to homepage
header("Location: /index.php", true, 302);
exit;
