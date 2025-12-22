<?php
session_start();
require_once "connection.inc.php";
require_once "src/EmailSender.php";
require_once "src/UserManager.php";
$config = require __DIR__ . "/config.php";
$cnx = getConnection();
$mailer = new EmailSender(); // Instantiate the EmailSender object
$userMgr = new UserManager($cnx); // UserManager object
date_default_timezone_set('Europe/Paris');  // so that the PHP server uses the same time zone as MySQL

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["email"])) {
    if (isset($_SESSION["blocking_time"]) && $_SESSION["blocking_time"] > time()) {
        $remaining = $_SESSION["blocking_time"] - time();
        $minutes = intdiv($remaining, 60);
        $seconds = $remaining % 60;
        if ($minutes > 0) {
            $message = "You must wait another $minutes minute" . ($minutes > 1 ? "s" : "") .
                    " and $seconds second" . ($seconds > 1 ? "s" : "");
        } else {
            $message = "You must wait $seconds second" . ($seconds > 1 ? "s" : "");
        }
    } else {
        $captchaResponse = $_POST["frc-captcha-response"] ?? null;
        if (!$userMgr->verifyCaptcha($captchaResponse)) {
            header("Location: forgot_password.php?error=captcha");
            exit;
        }
        $email = trim($_POST["email"]);

        // Verify that the email exist
        $stmt = $cnx->prepare("SELECT id, is_verified FROM user WHERE email = :email");
        $stmt->bindParam(":email", $email);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Generate a secure token
            $token = bin2hex(random_bytes(32));
            $tokenHash = hash("sha256", $token); // Verify the CNIL recommendation
            $expires_at = date("Y-m-d H:i:s", time() + 60); // 1 minute

            // Delete old tokens for avoid duplication, only one active token per user
            $deleteOld = $cnx->prepare("DELETE FROM user_token WHERE user_id = :user_id and type = 'password'");
            $deleteOld->bindParam(":user_id", $user["id"]);
            $deleteOld->execute();

            // Save in DB
            $stmt = $cnx->prepare("INSERT INTO user_token (user_id, token, expires_at, type) VALUES (:user_id, :token, :expires_at, 'password')");
            $stmt->bindParam(":user_id", $user["id"]);
            $stmt->bindParam(":token", $tokenHash);
            $stmt->bindParam(":expires_at", $expires_at);
            $stmt->execute();

            // Prepare the link
            $resetLink = $config["BASE_URL"] . 'password_reset.php?token=' . $token; // Adapt the link when the website will be online

            // Use the object method to send email
            $mailer->send($email, "Password reset", "Click this link to reset your password : 
                <a href='$resetLink'>$resetLink</a>");
        }
        $_SESSION["email"] = $email;
        $_SESSION["blocking_time"] = time() + 60;
        $_SESSION["success_message"] = "If you have an account associated with this email address, a link to reset your password has been sent.";
        header("location: forgot_password.php");
        exit();
    }

}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Forgot Password</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="p-auth">
<?php include "header.inc.php"; ?>

<main class="auth-container">
    <div class="auth-card">
        <img src="assets/images/img2brick.png" alt="img2brick logo" class="auth-logo">

        <h1>Reset Password</h1>
        <p class="auth-intro">Enter your email address and we'll send you a link to reset your password.</p>

        <div class="auth-messages">
            <?php if (isset($_SESSION["success_message"])): ?>
                <div class="alert alert-success">
                    <?php echo $_SESSION["success_message"]; ?>
                </div>
                <?php unset($_SESSION["success_message"]); ?>
            <?php endif; ?>

            <?php if (isset($_GET["error"])): ?>
                <div class="alert alert-error">
                    <?php
                    switch ($_GET["error"]) {
                        case "expired_or_invalid": echo "Invalid or expired link. You can retry."; break;
                        case "no_token": echo "No token provided. You can retry."; break;
                        case "captcha": echo "Invalid captcha. You can retry."; break;
                        default: echo "An error occurred."; break;
                    }
                    ?>
                </div>
            <?php endif; ?>

            <?php if(isset($message)): ?>
                <div class="alert alert-error">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
        </div>

        <form method="POST">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" name="email" id="email"
                       value="<?php echo isset($_GET['email']) ? htmlspecialchars($_GET['email']) : ''; ?>"
                       placeholder="e.g. master@builder.com" required>
            </div>

            <div class="frc-captcha" data-sitekey="FCMV6VL3726JO53T"></div>

            <button type="submit" class="btn-login">Send My Password Reset Link</button>
        </form>

        <div class="auth-footer">
            <p>Remembered your password? <a href="connection.php">Back to Login</a></p>
        </div>
    </div>
</main>

<script type="module" src="https://cdn.jsdelivr.net/npm/@friendlycaptcha/sdk@0.1.31/site.min.js" async defer></script>
<script nomodule src="https://cdn.jsdelivr.net/npm/@friendlycaptcha/sdk@0.1.31/site.compat.min.js" async defer></script>

<?php include "footer.inc.php"; ?>
</body>
</html>