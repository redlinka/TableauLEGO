<?php
session_start();
require_once "connection.inc.php";
require_once "src/EmailSender.php";
require_once "src/UserManager.php";
$cnx = getConnection();
$mailer = new EmailSender(); // Instantiate the EmailSender object
$userMgr = new UserManager($cnx); // UserManager object
$reset = null;

if (isset($_GET["token"])) {
    $token = $_GET["token"];
    $tokenHash = hash("sha256", $token);

    $stmt = $cnx->prepare("SELECT * FROM user_token WHERE token = :token AND expires_at > NOW() AND type = 'password'");
    $stmt->bindParam(":token", $tokenHash);
    $stmt->execute();
    $reset = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($reset) {
        if ($_SERVER["REQUEST_METHOD"] === "POST") {
            $result = $userMgr->changePassword($reset["user_id"], $_POST["newPwd"], $_POST["confirmPwd"]);
            if ($result !== true) {
                header("Location: password_reset.php?error=" . $result . "&token=" . urlencode($token));
                exit;
            }

            // Delete token
            $stmt = $cnx->prepare("DELETE FROM user_token WHERE token = :token");
            $stmt->bindParam(":token", $tokenHash);
            $stmt->execute();

            // Take the email corresponding to the user
            $stmt = $cnx->prepare("SELECT email FROM user WHERE id = :id");
            $stmt->bindParam(":id", $reset["user_id"]);
            $stmt->execute();
            $userEmail = $stmt->fetchColumn();

            if ($userEmail) {
                $mailer->send($userEmail, "Password change", "Your password has been changed. If you did not initiate this change, please contact support and/or change your password."); // Method send of Object EmailSender
            }
            header("Location: connection.php?password_reset=1");
            exit();
        }
    } else {
        header("Location: forgot_password.php?error=expired_or_invalid");
        exit();
    }
} else {
    header("Location: forgot_password.php?error=no_token");
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
    <title>Password change</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="p-auth">
    <?php include "header.inc.php"; ?>

    <main class="auth-container">
        <div class="auth-card">
            <img src="assets/images/img2brick.png" alt="img2brick logo" class="auth-logo">

            <h1>Set New Password</h1>
            <p class="auth-intro">Please choose a strong password to secure your account.</p>

            <div class="auth-messages">
                <?php if (isset($_GET["error"])): ?>
                    <div class="alert alert-error">
                        <?php
                        switch ($_GET["error"]) {
                            case "match": echo "Passwords don't match"; break;
                            case "password1": echo "Your password must be at least 12 characters long and contain uppercase, lowercase, numbers, and symbols."; break;
                            case "password2": echo "You cannot reuse your old password."; break;
                            default: echo "Something went wrong."; break;
                        }
                        ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($reset): ?>
                <form method="POST">
                    <div class="form-group">
                        <label for="newPwd">New Password</label>
                        <div class="password-wrapper">
                            <input type="password" name="newPwd" id="newPwd" placeholder="At least 12 characters" required>
                            <i class="fa-solid fa-eye fa-fw" id="toggleNewPwd"></i>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="confirmPwd">Confirm your new password</label>
                        <div class="password-wrapper">
                            <input type="password" name="confirmPwd" id="confirmPwd" placeholder="Repeat your password" required>
                            <i class="fa-solid fa-eye fa-fw" id="toggleConfirmPwd"></i>
                        </div>
                    </div>

                    <button type="submit" id="submitBtn" class="btn-login" disabled>Set New Password</button>
                </form>
            <?php else: ?>
                <div class="alert alert-error">This reset link is no longer valid.</div>
                <a href="forgot_password.php" class="btn-secondary">Request a new link</a>
            <?php endif; ?>
        </div>
    </main>

    <script>
        const submitBtn = document.getElementById("submitBtn");
        const newPwdInput = document.getElementById("newPwd");
        const toggleNewPwd = document.getElementById("toggleNewPwd");
        const confirmPwdInput = document.getElementById("confirmPwd");
        const toggleConfirmPwd = document.getElementById("toggleConfirmPwd");

        const setupToggle = (input, icon) => {
            icon.addEventListener("click", () => {
                if (input.type === "password") {
                    input.type = "text";
                    icon.classList.replace("fa-eye", "fa-eye-slash");
                } else {
                    input.type = "password";
                    icon.classList.replace("fa-eye-slash", "fa-eye");
                }
            });
        };

        setupToggle(newPwdInput, toggleNewPwd);
        setupToggle(confirmPwdInput, toggleConfirmPwd);

        function validatePasswords() {
            if (newPwdInput.value === "" || confirmPwdInput.value === "") {
                submitBtn.disabled = true;
            } else {
                submitBtn.disabled = newPwdInput.value !== confirmPwdInput.value;
            }
        }

        newPwdInput.addEventListener("input", validatePasswords);
        confirmPwdInput.addEventListener("input", validatePasswords);

        document.querySelector("form")?.addEventListener("submit", function(e) {
            if (newPwdInput.value !== confirmPwdInput.value) {
                e.preventDefault();
            }
        });
    </script>

    <?php include "footer.inc.php"; ?>
</body>
</html>
