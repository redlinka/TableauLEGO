<?php
// Page that authorizes a user to log in using a 6-digit token
session_start();
require_once "connection.inc.php";
require_once "src/EmailSender.php";
$cnx = getConnection();

// expiration of session variable
if (isset($_SESSION['verify_token_id_expires_at']) && $_SESSION['verify_token_id_expires_at'] < time()) {
    unset($_SESSION['verify_token_id']);
}

if (!isset($_SESSION['verify_token_id'])) {
    header("Location: connection.php");
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["token"])) {
    $token = $_POST["token"];
    // put a try catch code for security
    $stmt = $cnx->prepare("SELECT * FROM auth_token WHERE token = :token AND user_id = :user_id AND type = 'login' AND expires_at >= NOW()");
    $stmt->bindParam(":token", $token);
    $stmt->bindParam(":user_id", $_SESSION["verify_token_id"]);
    $stmt->execute();
    $auth = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($auth) {
        // initialize variable id in the session to keep the user logged in
        $_SESSION["user_id"] = $auth["user_id"]; // to adapt

        $delete = $cnx->prepare("DELETE FROM auth_token WHERE id = :id");
        $delete->bindParam(":id", $auth["id"]);
        $delete->execute();

        if (isset($_SESSION["email"])) {
            $mailer = new EmailSender(); // Instantiate the EmailSender object
            $sendEmail = $mailer->send($_SESSION["email"], "New Connection Detected", "Someone has logged into your account. If it wasn't you, please change your password immediately.");
        }
        unset($_SESSION['verify_token_id']);
        unset($_SESSION['email']);
        header("Location: index.php");
    } else {
        header("Location: connection.php?error=token");
    }
    exit();
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Two-factor authentication</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="p-auth">
<?php include "header.inc.php"; ?>

<main class="auth-container">
    <div class="auth-card">
        <img src="assets/images/img2brick.png" alt="img2brick logo" class="auth-logo">

        <h1>A2F</h1>
        <p class="auth-intro">Enter the 6-digit code sent to your email.</p>

        <div class="auth-messages">
            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-error">
                    <?php
                    switch ($_GET['error']) {
                        case 'invalid': echo "Invalid or expired token."; break;
                        case 'empty': echo "Please enter the code."; break;
                        case 'db': echo "Error while verifying the token."; break;
                        default: echo "An error occurred."; break;
                    }
                    ?>
                </div>
            <?php endif; ?>
        </div>

        <form method="POST">
            <div class="form-group">
                <input type="text" name="token" id="token"
                       placeholder="6-digit code" maxlength="6"
                       required class="token-input">
            </div>
            <button type="submit" class="btn-login">Verify</button>
        </form>

        <div class="auth-footer">
            <p>Didn't receive the code? <a href="connection.php">Try again</a></p>
        </div>
    </div>
</main>

<?php include "footer.inc.php"; ?>
</body>
</html>
