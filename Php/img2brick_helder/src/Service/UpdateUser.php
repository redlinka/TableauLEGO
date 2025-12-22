<?php
require_once "../../connexion.inc.php";
require_once "../Model/User.php";
require_once '../../config/phpmailer_config.php';
session_start();

if (!isset($_SESSION['user_obj'])) {
    header("Location: ../../public/login.php");
    exit;
}

$user = $_SESSION['user_obj'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');

    try {
        // BDD Update
        $stmt = $cnx->prepare("UPDATE users SET first_name = :first_name, last_name = :last_name, email = :email, address = :address, phone = :phone WHERE id = :id");
        $stmt->bindParam(':first_name', $first_name, PDO::PARAM_STR_CHAR);
        $stmt->bindParam(':last_name', $last_name);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':address', $address);
        $stmt->bindParam(':phone', $phone);
        $stmt->bindParam(':id', $user->user_id, PDO::PARAM_INT);
        $stmt->execute();

        // Update object session
        $user->first_name = $first_name;
        $user->last_name = $last_name;
        $user->email = $email;
        $user->phone = $phone;
        $user->address = $address;
        $_SESSION['user_obj'] = $user;

        // Send mail
        try {
            $subject = 'Your account information has been updated.';
            $body = "<h2>Hello {$first_name},</h2>
                    <p>Your account information has been successfully updated.</p>
                    <p>If it wasn't you, contact support immediately.</p>
                ";

            sendMail($email, $subject, $body, $user);
        } catch (Exception $e) {
            error_log("Mailer Error: " . $mail->ErrorInfo);
        }

        header("Location: ../../public/account.php?success=Updated information");
        exit;
    } catch (Exception $e) {
        header("Location: ../../public/account.php?error=" . urlencode($e->getMessage()));
        exit;
    }
}
