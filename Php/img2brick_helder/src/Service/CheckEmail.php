<?php
require_once "../../connexion.inc.php";
require_once "../Model/User.php";
require_once '../../config/phpmailer_config.php';

session_start();

if (!isset($_POST['email'])) {
    header("Location: ../../public/recover_form.php?error=" . urlencode("Data missing"));
}

try {
    $email = $_POST['email'];
    // Check user exists
    $stmt = $cnx->prepare("SELECT id, first_name FROM users WHERE email = :email");
    $stmt->bindParam(':email', $email, PDO::PARAM_STR);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        // Security: don't reveal whether email exists
        header("Location: ../../public/recover_form.php?success=If your email exists, you will receive reset instructions");
        exit;
    }

    // Generate token
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', time() + 60); // 1 min

    // Store token
    $update = $cnx->prepare("UPDATE users SET verification_token = :token, token_expires_at = :expires WHERE id = :id");
    $update->bindParam(':token', $token);
    $update->bindParam(':expires', $expires);
    $update->bindParam(':id', $user['id'], PDO::PARAM_INT);
    $update->execute();

    try {
        $resetLink = "http://localhost:8000/public/reset_password.php?token={$token}"; // adapte le domaine
        $subject = 'Password Reset Request';
        $body = "
                <h2>Hello {$user['first_name']},</h2>
                <p>You requested to reset your password. Click the link below to set a new password (valid for 1 min):</p>
                <p><a href='{$resetLink}'>Reset my password</a></p>
                <p>If you did not request this, you can safely ignore this email.</p>
            ";
        sendMail($email, $subject, $body, $user);
    } catch (Exception $e) {
        // log error but don't reveal to user
        error_log("Mailer Error: " . $mail->ErrorInfo);
    }

    header("Location: ../../public/recover_form.php?success=If your email exists, you will receive reset instructions");
    exit;
} catch (Exception $e) {
    error_log("ForgotPassword error: " . $e->getMessage());
    header("Location: ../../public/recover_form.php?error=An unexpected error occurred");
    exit;
}
