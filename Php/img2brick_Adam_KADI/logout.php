<?php
require __DIR__ . '/includes/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Destroying session data
$_SESSION = [];
session_destroy();

// Deleting cookies
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
unset(
  $_SESSION['2fa_pending_user_id'],
  $_SESSION['2fa_pending_email'],
  $_SESSION['2fa_code'],
  $_SESSION['2fa_expires_at'],
  $_SESSION['2fa_attempts']
);

// Back to index
header('Location: index.php');
exit;
