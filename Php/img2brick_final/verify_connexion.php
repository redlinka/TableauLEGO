<?php
// verify the connection of a verified account
session_start();
global $cnx;
include("./config/cnx.php");
require_once __DIR__ . '/includes/i18n.php';

// Validate input Ensure token presence
if (!isset($_GET['token'])) {
    http_response_code(400);
    die(tr('verify_connexion.no_token', 'No token provided.'));
}

try {
    // Query token Verify validity and expiration
    $stmt = $cnx->prepare("SELECT t.*, u.username, u.email 
                               FROM 2FA t
                               JOIN USER u ON t.user_id = u.user_id
                               WHERE t.verification_token = ?
                               LIMIT 1");
    $stmt->execute([$_GET['token']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$result) {
        http_response_code(400);
        die(tr('verify_connexion.invalid_link', 'Invalid or expired login link.'));
    }

    // Check expiration (1 minute)
    $now = new DateTime();
    $expiry = new DateTime($result['token_expire_at']);

    if ($now > $expiry) {
        http_response_code(400);
        die(tr('verify_connexion.expired', 'This login link has expired. Please try logging in again.'));
    }

    // --- SUCCESS: LOG THE USER IN ---

    // Delete used token
    $cnx->prepare("DELETE FROM `2FA` WHERE id_token = ?")->execute([$result['id_token']]);

    // Regenerate session ID Prevent fixation
    session_regenerate_id(true);
    $_SESSION['userId'] = $result['user_id'];
    $_SESSION['username'] = $result['username'];
    $_SESSION['email'] = $result['email'];

    // Link guest images to new session
    // Note: This relies on the user clicking the link in the same browser session
    $guestImages = [
        $_SESSION['step0_image_id'] ?? null,
        $_SESSION['step1_image_id'] ?? null,
        $_SESSION['step2_image_id'] ?? null,
        $_SESSION['step3_image_id'] ?? null,
        $_SESSION['step4_image_id'] ?? null
    ];

    foreach ($guestImages as $imgId) {
        if ($imgId) {
            // Adopt image only if currently orphaned
            $adoptStmt = $cnx->prepare("UPDATE IMAGE SET user_id = ? WHERE image_id = ? AND user_id IS NULL");
            $adoptStmt->execute([$result['user_id'], $imgId]);
        }
    }

    // Rotate CSRF
    csrf_rotate();
    addLog($cnx, "USER", "LOG", "in");
    // Redirect to intended destination
    if (isset($_SESSION['redirect_after_login'])) {
        header("Location:" . $_SESSION['redirect_after_login']);
    } else {
        header('Location: index.php');
    }
    exit;
} catch (PDOException $e) {
    http_response_code(500);
    die(tr('verify_connexion.db_error', 'Database error. Please try again later.'));
}
