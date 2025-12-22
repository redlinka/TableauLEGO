<?php
session_start();
require_once "connection.inc.php";
require_once "src/UserManager.php";
require_once "src/EmailSender.php";
$config = require __DIR__ . "/config.php";
$cnx = getConnection();
$mailer = new EmailSender(); // Instantiate the EmailSender object
$userMgr = new UserManager($cnx); // UserManager object
date_default_timezone_set('Europe/Paris');

$name = isset($_POST["name"]) ? trim($_POST["name"]) : "";
$email = isset($_POST["email"]) ? trim($_POST["email"]) : "";

if (isset($_SESSION["blockingTimeSignup"]) && $_SESSION["blockingTimeSignup"] <= time()) {
    unset($_SESSION["blockingTimeSignup"]);
}

// Captcha
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_SESSION["blockingTimeSignup"]) && $_SESSION["blockingTimeSignup"] > time()) {
        $blocked = 1;
    }
    if (empty($blocked)) {
        $captchaResponse = $_POST["frc-captcha-response"] ?? null;
        if (!$captchaResponse) {
            $errors[] = "captcha_required";
        }
        if (!$userMgr->verifyCaptcha($captchaResponse)) {
            $errors[] = "captcha_invalid";
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["name"]) && isset($_POST["email"]) && isset($_POST["pwd"]) && empty($blocked)) {
    $password = $_POST["pwd"];

    if ($name === "" || $email === "" || $password === "") {
        $errors[] = "empty";
    }

    if (strlen($name) < 2 || strlen($name) > 50) {
        $errors[] = "name";
    }

    if (strlen($email) > 50) {
        $errors[] = "email";
    }

    // pwd verification
    if (!$userMgr->validatePassword($password)) {
        $errors[] = "password";
    }
    if (empty($errors)) {
        $hashedPassword = password_hash($password, PASSWORD_ARGON2ID); // hashing algorithm recommended by the CNIL

        // verify if an account with this email already exists
        try {
            $user = $userMgr->getUserByEmail($email);
            if ($user) {
                if ($user["is_verified"] == 1) {
                    $resetLink = $config["BASE_URL"] . 'forgot_password.php?email=' . urlencode($email);
                    $message = "
                        <p>Hello,</p>
                        <p>You tried to create an account with this e-mail address, but an account already exists.</p>
                        <p>If you forgot your password, you can reset it using this link:</p>
                        <p><a href='$resetLink'>$resetLink</a></p>
                        <p>If you did not request this registration, please ignore this message.</p>
                        <br>
                        <p>The Img2Brick Team</p>";
                    $mailer->send($email, "Account already exists", $message);
                    $_SESSION["blockingTimeSignup"] = time() + 60;
                    header("Location: connection.php?registration_email=true");
                    exit();
                } else {
                    // Delete the unverified account and recreate it
                    $delete = $cnx->prepare("DELETE FROM user WHERE email = :email");
                    $delete->bindParam(":email", $email);
                    $delete->execute();
                }
            }

            // add the account on the DB
            $query = $cnx->prepare("INSERT INTO user (name, email, password) VALUES(:name, :email, :password)");
            $query->bindParam(":name", $name);
            $query->bindParam(":email", $email);
            $query->bindParam(":password", $hashedPassword);
            $query->execute();

            $user_id = $cnx->lastInsertId(); // Retrieves the ID of the new user

            // Generate a secure token
            $token = bin2hex(random_bytes(32));
            $tokenHash = hash("sha256", $token); // Verify the CNIL recommendation
            $expires_at = date("Y-m-d H:i:s", time() + 60); // 1 minute

            // Delete old tokens for avoid duplication, only one active token per user
            $deleteOld = $cnx->prepare("DELETE FROM user_token WHERE user_id = :user_id AND type = 'email'");
            $deleteOld->bindParam(":user_id", $user_id);
            $deleteOld->execute();

            // Save in DB
            $stmt = $cnx->prepare("INSERT INTO user_token (user_id, token, expires_at, type) VALUES (:user_id, :token, :expires_at, 'email')");
            $stmt->bindParam(":user_id", $user_id);
            $stmt->bindParam(":token", $tokenHash);
            $stmt->bindParam(":expires_at", $expires_at);
            $stmt->execute();

            // Prepare the link
            $verificationLink = $config["BASE_URL"] . 'verify_email.php?token=' . $token; // Adapt the link when the website will be online

            // Send email with PHPMailer
            $mailer->send($email, "Email Verification", "Click this link to verify your email : 
                    <a href='$verificationLink'>$verificationLink</a>");
            $_SESSION["blockingTimeSignup"] = time() + 60;
            header("Location: connection.php?registration_email=true");
            exit();
        } catch (PDOException $e) {
            //echo $e->getMessage(); // in dev
            header("Location: registration.php?error"); // in prod
        }
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
    <title>Registration</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="p-auth">
<?php include "header.inc.php"; ?>

<main class="auth-container">
    <div class="auth-card">
        <img src="assets/images/img2brick.png" alt="img2brick logo" class="auth-logo">

        <h1>Create Account</h1>

        <div class="auth-messages">
            <?php if (isset($errors)): ?>
                <div class="alert alert-error">
                    <?php foreach ($errors as $error): ?>
                        <p><?php
                            switch ($error) {
                                case "empty": echo "Please fill out the form."; break;
                                case "name": echo "Name: 2 to 50 characters."; break;
                                case "email": echo "Email: max 50 characters."; break;
                                case "password": echo "Password must be 12+ chars, mixed cases, numbers & symbols."; break;
                                case "captcha_required": echo "Please complete the captcha."; break;
                                case "captcha_invalid": echo "Captcha failed."; break;
                                default: echo "An error occurred."; break;
                            }
                            ?></p>
                    <?php endforeach; ?>
                </div>
            <?php elseif (isset($_SESSION["blockingTimeSignup"])): ?>
                <div class="alert alert-error">
                    Please wait <?php echo max(0, $_SESSION["blockingTimeSignup"] - time()); ?> seconds.
                </div>
            <?php endif; ?>
        </div>

        <form method="POST" action="">
            <div class="auth-intro">
                <p>Already have an account? <a href="connection.php">Log in</a></p>
            </div>

            <div class="form-group">
                <label for="name">Name :</label>
                <input type="text" name="name" id="name" placeholder="Full Name" required value="<?php echo htmlspecialchars($name); ?>">
            </div>

            <div class="form-group">
                <label for="email">Email :</label>
                <input type="email" name="email" id="email" placeholder="Email" required value="<?php echo htmlspecialchars($email); ?>">
            </div>

            <div class="form-group">
                <label for="pwd">Password :</label>
                <div class="password-wrapper">
                    <input type="password" name="pwd" id="pwd" placeholder="Strong password" required>
                    <i class="fa-solid fa-eye fa-fw" id="togglePwd"></i>
                </div>
            </div>

            <div class="frc-captcha" data-sitekey="FCMV6VL3726JO53T"></div>

            <button type="submit" class="btn-login" style="background-color: var(--primary-red);">Sign up</button>
        </form>
    </div>
</main>

<script>
    const pwdInput = document.getElementById("pwd");
    const togglePwd = document.getElementById("togglePwd");

    togglePwd.addEventListener("click", () => {
        if (pwdInput.type === "password") {
            pwdInput.type = "text";
            togglePwd.classList.remove("fa-eye");
            togglePwd.classList.add("fa-eye-slash");
        } else {
            pwdInput.type = "password";
            togglePwd.classList.remove("fa-eye-slash");
            togglePwd.classList.add("fa-eye");
        }
    });
</script>
<script type="module" src="https://cdn.jsdelivr.net/npm/@friendlycaptcha/sdk@0.1.31/site.min.js" async defer></script>
<script nomodule src="https://cdn.jsdelivr.net/npm/@friendlycaptcha/sdk@0.1.31/site.compat.min.js" async defer></script>

<?php include "footer.inc.php"; ?>
</body>
</html>