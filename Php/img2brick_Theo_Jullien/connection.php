<?php
session_start();
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Connection</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="p-auth">
    <?php include "header.inc.php"; ?>
    <main class="auth-container">
        <div class="auth-card">

            <div class="auth-messages">
                <?php if (isset($_GET["error"])): ?>
                    <div class="alert alert-error">
                        <?php
                        switch ($_GET["error"]) {
                            case "empty": echo "Please fill in all fields"; break;
                            case "userNotFound": echo "The email or password provided is incorrect"; break;
                            case "token": echo "Invalid or expired code"; break;
                            case "token2": echo "Invalid or expired token"; break;
                            case "email": echo "An error occurred while sending email containing the authentification or verification code"; break;
                            case "too_many_emails": echo "You have requested too many emails in a short time. Please wait a minute before trying again"; break;
                            case "db_register": echo "An error occurred while completing the registration"; break;
                            default: echo "An error occurred while connecting"; break;
                        }
                        ?>
                    </div>
                <?php elseif (isset($_GET["success"])): ?>
                    <div class="alert alert-success">Connection successful</div>
                <?php elseif (isset($_GET["registration_email"])): ?>
                    <div class="alert alert-success">Please follow the instructions received by email to complete the registration.</div>
                <?php elseif (isset($_GET["registration_success"])): ?>
                    <div class="alert alert-success">Registration completed</div>
                <?php elseif (isset($_GET["password_reset"])): ?>
                    <div class="alert alert-success">Your password has been reset. You can now log in.</div>
                <?php elseif (isset($_GET["blocking_time"]) && isset($_SESSION["blockingTimeLogin"]) && $_SESSION["blockingTimeLogin"] > time()): ?>
                    <div class="alert alert-error">
                        <?php
                        $remaining = $_SESSION["blockingTimeLogin"] - time();
                        $minutes   = intdiv($remaining, 60);
                        $seconds   = $remaining % 60;
                        if ($minutes > 0) {
                            echo "You must wait another $minutes minute" . ($minutes > 1 ? "s" : "") .
                                    " and $seconds second" . ($seconds > 1 ? "s" : "");
                        } else {
                            echo "You must wait $seconds second" . ($seconds > 1 ? "s" : "");
                        }
                        ?>
                    </div>
                <?php endif; ?>
            </div>

            <form method="POST" action="verify_connection.php">
                <img src="assets/images/img2brick.png" alt="img2brick logo" class="auth-logo">

                <div class="auth-intro">
                    <p>Don't have an account? <a href="registration.php">Create your account</a></p>
                </div>

                <div class="form-group">
                    <label for="email">Email : </label>
                    <input type="email" name="email" id="email" placeholder="Email" required>
                </div>

                <div class="form-group">
                    <label for="pwd">Password : </label>
                    <div class="password-wrapper">
                        <input type="password" name="pwd" id="pwd" placeholder="Password" required>
                        <i class="fa-solid fa-eye fa-fw" id="togglePwd"></i>
                    </div>
                </div>

                <?php if (isset($_SESSION['captcha_required']) && $_SESSION['captcha_required']) : ?>
                    <div class="frc-captcha" data-sitekey="FCMV6VL3726JO53T"></div>
                <?php endif; ?>

                <button type="submit" class="btn-login">Login</button>
            </form>

            <div class="auth-footer">
                <a href="forgot_password.php" class="forgot-link">Forgot your password ?</a>
            </div>
        </div>
    </main>

    <script>
        const pwdInput = document.getElementById("pwd");
        const togglePwd = document.getElementById("togglePwd");

        togglePwd.addEventListener("click", () => {
            if (pwdInput.type === "password") {
                pwdInput.type = "text";
                togglePwd.classList.remove("fa-eye");
                togglePwd.classList.add("fa-eye-slash"); // open eye
            } else {
                pwdInput.type = "password";
                togglePwd.classList.remove("fa-eye-slash");
                togglePwd.classList.add("fa-eye"); // closed eye
            }
        });
    </script>
    <script type="module" src="https://cdn.jsdelivr.net/npm/@friendlycaptcha/sdk@0.1.31/site.min.js" async defer></script>
    <script nomodule src="https://cdn.jsdelivr.net/npm/@friendlycaptcha/sdk@0.1.31/site.compat.min.js" async defer></script>
    <?php include "footer.inc.php"; ?>
</body>
</html>