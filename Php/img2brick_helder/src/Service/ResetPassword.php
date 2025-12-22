<?php

require_once "../../connexion.inc.php";
require_once "../Model/User.php";
require_once "../Model/Order.php";
require_once '../../config/phpmailer_config.php';

session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../../public/account.php");
    exit;
}

$token = $_POST['token'] ?? '';
$new_password = $_POST['new_password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

if (!$token || !$new_password || !$confirm_password) {
    header("Location: ../../public/account.php?error=Missing data");
    exit;
}

// Check password pattern
$passwordPattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).*$/';
if (!preg_match($passwordPattern, $new_password)) {
    header("Location: ../../public/reset_password.php?token=" . urlencode($token) . "&error=Password must include at least one lowercase letter, one uppercase letter, one number, and one special character");
    exit;
}

if ($new_password !== $confirm_password) {
    header("Location: ../../public/reset_password.php?token=" . urlencode($token) . "&error=Passwords do not match");
    exit;
}

try {
    $cnx->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $cnx->prepare("SELECT id, first_name, email, token_expires_at FROM users WHERE verification_token = :token");
    $stmt->bindParam(':token', $token, PDO::PARAM_STR);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        // Clear token
        $update = $cnx->prepare("UPDATE users SET verification_token = NULL, token_expires_at = NULL WHERE id = :id");
        $update->bindParam(':id', $_SESSION['user_obj']->user_id, PDO::PARAM_INT);
        $update->execute();
        header("Location: ../../public/account.php?error=Invalid or expired token");
        exit;
    }

    if (!$user['token_expires_at'] || strtotime($user['token_expires_at']) < time()) {
        // Clear token
        $update = $cnx->prepare("UPDATE users SET verification_token = NULL, token_expires_at = NULL WHERE id = :id");
        $update->bindParam(':id', $user['id'], PDO::PARAM_INT);
        $update->execute();
        header("Location: ../../public/account.php?error=Token expired");
        exit;
    }

    // Update password, clear token
    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
    $update = $cnx->prepare("UPDATE users SET password_hash = :password, verification_token = NULL, token_expires_at = NULL WHERE id = :id");
    $update->bindParam(':password', $hashed);
    $update->bindParam(':id', $user['id'], PDO::PARAM_INT);
    $update->execute();

    // Send confirmation mail
    try {

        $body = "<h2>Hello {$user['first_name']},</h2>
                <p>Your password has been successfully reset. If you did not request this change, please contact support immediately.</p>
            ";
        $subject = 'Your password has been reset';
        sendMail($user['email'], $subject, $body, $user);

        if (isset($_SESSION["order_obj"])) {
            header("Location: ../../public/order.php?success_up=Password updated");
        } else {
            header("Location: ../../public/account.php?success=Password updated");
        }
        exit;
    } catch (Exception $e) {
        error_log("Mailer Error (reset confirm): " . $mail->ErrorInfo);

        //Depending on the current page (order / sign)
        if (isset($_SESSION["order_obj"])) {
            header("Location: ../../public/order.php?success_up=Password updated (no mail send)");
        } else {
            header("Location: ../../public/account.php?success=Password updated (no mail send)");
        }
    }
} catch (Exception $e) {
    error_log("ResetPassword error: " . $e->getMessage());

    //Depending on the current page (order / sign)
    if (isset($_SESSION["order_obj"])) {
        header("Location: ../../public/order.php?error_in=An unexpected error occurred");
    } else {
        header("Location: ../../public/account.php?error=An unexpected error occurred");
    }
    exit;
}
