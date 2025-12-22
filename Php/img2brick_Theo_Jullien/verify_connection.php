<?php
// Page that verifies the connection with a 6-digit Code (A2F)
session_start();
require_once "connection.inc.php";
require_once "src/UserManager.php";
require_once "src/EmailSender.php";
$config = require __DIR__ . "/config.php";
$cnx = getConnection();
$mailer = new EmailSender(); // Instantiate the EmailSender object
$userMgr = new UserManager($cnx); // UserManager object
date_default_timezone_set('Europe/Paris'); // so that the PHP server uses the same time zone as MySQL

if (!isset($_SESSION['failedAttempts']) && !isset($_SESSION['blockingTimeLogin']) && !isset($_SESSION['captcha_required'])) {
    $_SESSION['failedAttempts'] = 0;
    $_SESSION['blockingTimeLogin'] = 0;
    $_SESSION['captcha_required'] = false;
}

if ($_SESSION['blockingTimeLogin'] > time()) {
    header('Location: connection.php?blocking_time=1');
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["email"]) && isset($_POST["pwd"])) {
    if ($_SESSION["captcha_required"]) {
        $captchaResponse = $_POST["frc-captcha-response"] ?? null;
        if (!$userMgr->verifyCaptcha($captchaResponse)) {
            $_SESSION['failedAttempts']++;
            if ($_SESSION['failedAttempts'] >= 3) {
                $_SESSION['blockingTimeLogin'] = time() + 60; // 1min
                $_SESSION['failedAttempts'] = 0;
                header("Location: connection.php?blocking_time=1");
                exit;
            }
            header('Location: connection.php?error=captcha');
            exit;
        }
    }

    $email = trim($_POST["email"]);
    $pwd = $_POST["pwd"];
    if ($email === "" || $pwd === "") {
        header("Location: connection.php?error=empty");
        exit;
    }

    try {
        $user = $userMgr->getUserByEmail($email);
        if ($user && password_verify($pwd, $user['password'])) {
            $_SESSION['blockingTimeLogin'] = 0;
            $_SESSION['failedAttempts'] = 0;
            $_SESSION['verify_token_id'] = $user['id'];
            $_SESSION['verify_token_id_expires_at'] = time() + 60;
            $_SESSION['email'] = $email;
            $_SESSION['captcha_required'] = false;

            // 1 email per minute
            $query = $cnx->prepare("SELECT last_email_sent_at FROM user WHERE id = :id");
            $query->bindParam(":id", $user['id']);
            $query->execute();
            $lastSent = $query->fetchColumn();

            if ($lastSent && strtotime($lastSent) > time() - 60) {
                header("Location: connection.php?error=too_many_emails");
                exit();
            }


            $token = random_int(100000, 999999); // 6-digit token
            $expiresAt = date("Y-m-d H:i:s", time() + 60); // valid for 1 min

            // Delete old tokens for avoid duplication, only one active token per user
            $deleteOld = $cnx->prepare("DELETE FROM auth_token WHERE user_id = :user_id");
            $deleteOld->bindParam(":user_id", $user["id"]);
            $deleteOld->execute();

            $type = 'login';
            $subject = "Two-factor authentication";
            $body = "Here is your 6-digit authentication code : $token";
            // if user has not yet verify his account
            if (!$user['is_verified']) {
                $_SESSION['verify_user_id'] = $user['id'];
                $_SESSION['verify_user_id_expires_at'] = time() + 60;
                $type = 'verify_email';
                $subject = "Code to confirm your email";
                $body = "Here is your 6-digit confirmation code : $token";
            }

            // Save in DB the token of email login
            $stmt = $cnx->prepare("INSERT INTO auth_token (user_id, token, expires_at, type) VALUES (:id, :token, :expires_at, :type)");
            $stmt->bindParam(":id", $user['id']);
            $stmt->bindParam(":token", $token);
            $stmt->bindParam(":expires_at", $expiresAt);
            $stmt->bindParam(":type", $type);
            $stmt->execute();

            $mailer = new EmailSender(); // Instantiate the EmailSender object
            // Send email containing token
            $sendEmail = $mailer->send($email, $subject, $body);

            $update = $cnx->prepare("UPDATE user SET last_email_sent_at = NOW() WHERE id = :id");
            $update->bindParam(":id", $user['id']);
            $update->execute();
            if (!$sendEmail) {
                header("Location: connection.php?error=email");
                exit;
            }

            ($user['is_verified']) ? header("Location: verify_token.php") : header("Location: verify_email_connection.php");
        } else {
            $_SESSION['failedAttempts']++;
            if ($_SESSION['failedAttempts'] == 2) {
                $_SESSION['captcha_required'] = true;
            }
            if ($_SESSION['failedAttempts'] >= 3) {
                $_SESSION['blockingTimeLogin'] = time() + 60; // 1min
                $_SESSION['failedAttempts'] = 0;
                header("Location: connection.php?blocking_time=1");
                exit;
            }
            header("Location: connection.php?error=userNotFound");
        }
        exit;
    } catch (PDOException $e) {
        //echo $e->getMessage(); // in dev
        header("Location: connection.php?error"); // in prod
    }
} else {
    header("Location: connection.php?error=empty");
    exit;
}