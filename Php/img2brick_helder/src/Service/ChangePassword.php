<?php
require_once "../../connexion.inc.php";
require_once "../Model/User.php";
require_once '../../config/phpmailer_config.php';

session_start();

if (!isset($_SESSION['user_obj'])) {
    header("Location: ../../public/sign_in.php");
    exit;
}
$user = $_SESSION['user_obj'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Validate new password pattern
    $passwordPattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).*$/';
    if (!preg_match($passwordPattern, $new_password)) {
        header("Location: ../../public/change_password.php?error=Password must include at least one lowercase letter, one uppercase letter, one number, and one special character");
        exit;
    }

    if ($new_password !== $confirm_password) {
        header("Location: ../../public/change_password.php?error=Passwords do not match");
        exit;
    }

    try {
        // Verify current password
        $stmt = $cnx->prepare("SELECT password_hash FROM users WHERE id = :id");
        $stmt->bindParam(':id', $user->user_id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!password_verify($current_password, $row['password_hash'])) {
            header("Location: ../../public/change_password.php?error=Current password is incorrect");
            exit;
        }

        // Update password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $update = $cnx->prepare("UPDATE users SET password_hash = :password WHERE id = :id");
        $update->bindParam(':password', $hashed_password);
        $update->bindParam(':id', $user->user_id, PDO::PARAM_INT);
        $update->execute();

        // Send mail notification
        try {
            $subject = 'Your password has been changed';
            $body = "
                <h2>Hello {$user->first_name},</h2>
                <p>Your password has been successfully updated.</p>
                <p>If you did not perform this action, please contact support immediately.</p>
            ";
            sendMail($user->email, $subject, $body, $user);
        } catch (Exception $e) {
            error_log("Mailer Error: " . $mail->ErrorInfo);
        }

        header("Location: ../../public/account.php?success=Password updated");
        exit;
    } catch (Exception $e) {
        header("Location: ../../public/change_password.php?error=" . urlencode("Error try again"));
        exit;
    }
}
