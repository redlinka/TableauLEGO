<?php
session_start();
require_once "connection.inc.php";
require_once "src/UserManager.php";
require_once "src/EmailSender.php";
$config = require __DIR__ . "/config.php";
date_default_timezone_set('Europe/Paris');

if (isset($_SESSION["user_id"]) && isset($_SESSION["temp_user_id"])) {
    unset($_SESSION["temp_user_id"]);
}

// Access allowed for fully logged in OR users pending verification
$currentUserId = $_SESSION["user_id"] ?? $_SESSION["temp_user_id"] ?? null;

// Redirect to login if not authenticated
if (!$currentUserId) {
    header("Location: connection.php");
    exit;
}

$cnx = getConnection();
$userMgr = new UserManager($cnx);
$mailer = new EmailSender();
$user = $userMgr->getUserById($currentUserId);

// ======= UPDATE PROFILE =======
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_profile"])) {
    $email = trim($_POST["email"]);
    $name = trim($_POST["name"]);
    $address = trim($_POST["shipping_address"]);

    //Check if the email is already used by SOMEONE ELSE
    $existingUser = $userMgr->getUserByEmail($email);
    if ($existingUser && (int)$existingUser['id'] !== (int)$currentUserId) {
        header("Location: user.php?error=email_taken");
        exit;
    }
    if ($email !== $user['email']) {
        $lastSent = $user['last_email_sent_at'] ? strtotime($user['last_email_sent_at']) : 0;
        $delay = 60; // 1 minute
        if ((time() - $lastSent) < $delay) {
            $remaining = $delay - (time() - $lastSent);
            header("Location: user.php?error=rate_limit&wait=" . $remaining);
            exit;
        }
        // 1. Update DB (is_verified becomes 0 via the updateProfile method)
        $userMgr->updateProfile($currentUserId, $name, $email, $address);

        // Update last_email_sent_at in DB
        $stmtUpdate = $cnx->prepare("UPDATE user SET last_email_sent_at = NOW() WHERE id = :id");
        $stmtUpdate->bindParam(":id", $currentUserId);
        $stmtUpdate->execute();

        // 2. Token Generation
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash("sha256", $token);
        $expires_at = date("Y-m-d H:i:s", time() + 60); // 1 minute

        // Cleanup old tokens
        $deleteOld = $cnx->prepare("DELETE FROM user_token WHERE user_id = :user_id AND type = 'email'");
        $deleteOld->bindParam(":user_id", $currentUserId);
        $deleteOld->execute();

        // Save new token
        $stmt = $cnx->prepare("INSERT INTO user_token (user_id, token, expires_at, type) VALUES (:user_id, :token, :expires_at, 'email')");
        $stmt->bindParam(":user_id", $currentUserId);
        $stmt->bindParam(":token", $tokenHash);
        $stmt->bindParam(":expires_at", $expires_at);
        $stmt->execute();

        // 3. Send Email
        $verificationLink = $config["BASE_URL"] . 'verify_email.php?token=' . $token;
        $mailer->send($email, "Verify your new email address", "Click here to verify your new email: <a href='$verificationLink'>$verificationLink</a>");

        // 4. Switch Session to Restricted Access
        $_SESSION["temp_user_id"] = $currentUserId;
        unset($_SESSION["user_id"]);

        header("Location: user.php?msg=success_reverify");
    } else {
        // Standard update without email change
        $userMgr->updateProfile($currentUserId, $name, $email, $address);
        header("Location: user.php?msg=profile_updated");
    }
    exit;
}

// ======= PASSWORD CHANGE =======
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["change_password"])) {
    $current = $_POST["currentPwd"];
    $new = $_POST["newPwd"];
    $confirm = $_POST["confirmPwd"];

    // Verify current password first
    if (!password_verify($current, $user["password"])) {
        header("Location: user.php?pwd_error=current");
        exit;
    }

    $result = $userMgr->changePassword($currentUserId, $new, $confirm);

    if ($result !== true) {
        header("Location: user.php?pwd_error=" . $result);
        exit;
    }

    header("Location: user.php?msg=pwd_ok");
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Account - Img2Brick</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="p-user">
<?php include "header.inc.php"; ?>

<main class="container">
    <header class="page-title">
        <h1>My Account</h1>
        <p>Manage your profile and security settings.</p>
    </header>

    <div class="auth-messages">
        <?php if (isset($_GET["msg"])): ?>
            <div class="alert alert-success">
                <?php
                if ($_GET["msg"] === "profile_updated") echo "Profile information updated.";
                elseif ($_GET["msg"] === "success_reverify") echo "Profile updated. Please check your inbox to verify your new email.";
                elseif ($_GET["msg"] === "pwd_ok") echo "Password successfully changed.";
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET["error"])): ?>
            <div class="alert alert-error">
                <?php
                if ($_GET["error"] === "email_taken") echo "This email is already in use by another account.";
                elseif ($_GET["error"] === "rate_limit") echo "Please wait ".intval($_GET['wait'])." seconds before requesting another email change.";
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION["temp_user_id"]) && !isset($_SESSION["user_id"])): ?>
            <div class="alert alert-warning">
                <strong>Action required:</strong> Please check your inbox (<?= htmlspecialchars($user['email']) ?>) to verify your new email address.
                Your account access is restricted.
            </div>
        <?php endif; ?>
    </div>

    <div class="user-grid">
        <section class="user-card">
            <h3>Personal Information</h3>
            <form method="POST">
                <div class="form-group">
                    <label for="name">Full Name</label>
                    <input type="text" name="name" id="name" value="<?= htmlspecialchars($user["name"]) ?>" required>
                </div>

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" name="email" id="email" value="<?= htmlspecialchars($user["email"]) ?>" required>
                </div>

                <div class="form-group">
                    <label for="shipping_address">Default Shipping Address</label>
                    <textarea name="shipping_address" id="shipping_address" rows="3"><?= htmlspecialchars($user["shipping_address"] ?? "") ?></textarea>
                </div>

                <button type="submit" name="update_profile" class="btn-login">Update Profile</button>
            </form>
        </section>

        <section class="user-card">
            <h3>Security</h3>
            <p class="label-desc">Protect your account by using a strong password.</p>

            <button type="button" class="btn-secondary" onclick="document.getElementById('pwdForm').style.display='block';">
                Change Password
            </button>

            <div id="pwdForm" class="pwd-collapse" style="<?= isset($_GET['pwd_error']) ? 'display:block;' : 'display:none;' ?> margin-top:20px;">
                <?php if (isset($_GET["pwd_error"])): ?>
                    <div class="alert alert-error">
                        <?php
                        switch ($_GET["pwd_error"]) {
                            case "current": echo "Current password is incorrect."; break;
                            case "match": echo "Passwords do not match."; break;
                            case "password1": echo "New password does not meet requirements."; break;
                            case "password2": echo "You cannot reuse your current password."; break;
                            default: echo "An error occurred."; break;
                        }
                        ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="form-group">
                        <label>Current Password</label>
                        <input type="password" name="currentPwd" required>
                    </div>
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="newPwd" required>
                    </div>
                    <div class="form-group">
                        <label>Confirm New Password</label>
                        <input type="password" name="confirmPwd" required>
                    </div>

                    <div class="row">
                        <button type="button" class="btn-cancel" onclick="document.getElementById('pwdForm').style.display='none';">Cancel</button>
                        <button type="submit" name="change_password" class="btn-login" style="background-color:var(--primary-red);">Update Password</button>
                    </div>
                </form>
            </div>
        </section>
    </div>
</main>

<?php include "footer.inc.php"; ?>
</body>
</html>