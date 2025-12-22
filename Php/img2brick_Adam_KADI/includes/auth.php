<?php
// includes/auth.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


//Check if user is logged in.

function require_login(): void
{
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}


//Check if user is admin. I wanted to implement an admin page but I didn't have enough time.

function require_admin(): void
{
    require_login();

    if (empty($_SESSION['is_admin'])) {
        http_response_code(403);
        echo "<h1>" . t('access') . "</h1>";
        echo "<p>" . t('admin') . "</p>";
        exit;
    }
}