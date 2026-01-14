<?php
session_start();
global $cnx;
include("./config/cnx.php");

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Validate session integrity
    if (!csrf_validate($_POST['csrf'] ?? null)) {
        http_response_code(400);
        die('Invalid form submission.');
    }

    // Verify captcha to block bots
    if (!validateTurnstile()['success']) {
        http_response_code(403);
        $errors[] = 'Access denied to bots or internal error';
    } else {
        try {
            // Fetch user by email or username
            $stmt = $cnx->prepare("SELECT * FROM USER WHERE (email = ? OR username = ?)");
            $stmt->execute([$_POST['userid'], $_POST['userid']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Delay execution to mitigate brute-force
            usleep(150000);

            if (!$user) {
                $errors[] = 'Invalid username or password';
            } else if (!password_verify($_POST['password'], $user['password'])) {

                $errors[] = 'Invalid username or password';

            } else if ((int)$user['is_verified'] === 0) {

                // Verify account activation status
                session_regenerate_id(true);

                $_SESSION['tempId'] = $user['user_id'];
                $_SESSION['unverified_email'] = $user['email'];

                $token = bin2hex(random_bytes(32));
                $expire_at = date('Y-m-d H:i:s', time() + 60);

                $cleanup = $cnx->prepare("DELETE FROM 2FA WHERE user_id = ?");
                $cleanup->execute([$user['user_id']]);

                $ins = $cnx->prepare("INSERT INTO 2FA (user_id, verification_token, token_expire_at) VALUES (?, ?, ?)");
                $ins->execute([$user['user_id'], $token, $expire_at]);

                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
                $link = $protocol . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/verify_account.php?token=' . $token;

                $emailBody = "
                    <div style='font-family: Arial, sans-serif; padding: 20px; border: 1px solid #e0e0e0; border-radius: 8px; max-width: 600px;'>
                        <h2 style='color: #0d6efd;'>Activate Your Account</h2>
                        <p>It looks like your account isn't active yet. Click below to verify your email:</p>
                        <p style='text-align: center;'>
                            <a href='{$link}' style='display: inline-block; background-color: #0d6efd; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; font-weight: bold;'>Verify My Account</a>
                        </p>
                    </div>";

                sendMail($user['email'], 'Activate your Img2Brick account', $emailBody);

                $errors[] = 'Your account is not activated. A new verification email has been sent to your inbox. If you didn\'t receive it, click <a href="creation_mail.php">here</a>.';                csrf_rotate();
            } else {
                // Update password hash if algorithm changed
                if (password_needs_rehash($user['password'], $_ENV['ALGO'])) {
                    $newHash = password_hash($_POST['password'], $_ENV['ALGO']);
                    $upd = $cnx->prepare("UPDATE USER SET password = ? WHERE user_id = ?");
                    $upd->execute([$newHash, $user['user_id']]);
                }

                // Generate verification token
                $token = bin2hex(random_bytes(32));
                $expire_at = date('Y-m-d H:i:s', time() + 60); // 1min

                // delete old token
                $cleanup = $cnx->prepare("DELETE FROM `2FA` WHERE user_id = ?");
                $cleanup->execute([$user['user_id']]);

                // Store token in database
                $ins = $cnx->prepare("INSERT INTO 2FA (user_id, verification_token, token_expire_at) VALUES (?, ?, ?)");
                $ins->execute([$user['user_id'], $token, $expire_at]);

                // Construct magic link
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
                $domain = $_SERVER['HTTP_HOST'];
                $link = $protocol . $domain . dirname($_SERVER['PHP_SELF']) . '/verify_connexion.php?token=' . $token;

                // Format email body
                $emailBody = "
                        <div style='font-family: Arial, sans-serif; padding: 20px; border: 1px solid #e0e0e0; border-radius: 8px; max-width: 600px;'>
                            <h2 style='color: #0d6efd;'>Secure Login Link 🔑</h2>
                            <p>We received a login attempt for your Img2Brick account. To complete the login process, please click the button below:</p>
                            <p style='text-align: center;'>
                                <a href='{$link}' style='display: inline-block; background-color: #0d6efd; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; font-weight: bold;'>Log In</a>
                            </p>
                            <p style='color: #6c757d; font-size: 12px; margin-top: 20px;'>If the button doesn't work, copy this link: {$link}</p>
                            <p style='color: #6c757d; font-size: 12px;'>This link expires in 1 minute.</p>
                        </div>";

                // Send authentication email
                sendMail(
                        $user['email'],
                        'Complete your login',
                        $emailBody
                );

                // Redirect to check mail page
                header("Location: connexion_mail.php");
                exit;
            }

        } catch (PDOException $e) {
            http_response_code(500);
            //$errors[] = 'Database error: ' . $e->getMessage();
            $errors[] = 'Database error. Please try again later.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log in</title>
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<?php include("./includes/navbar.php"); ?>

<div class="container bg-light py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">

            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <h2 class="text-center fw-bold mb-4" data-i18n="login.title">Log In</h2>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0 ps-3">
                                <?php foreach ($errors as $error): ?>
                                    <li><?= $error?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form action="" method="post">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_get(), ENT_QUOTES, 'UTF-8') ?>">

                        <div class="mb-3">
                            <label for="userid" class="form-label" data-i18n="login.userid_label">Email or Username</label>
                            <input type="text" class="form-control" name="userid" id="userid"
                                   placeholder="Enter your email or username" data-i18n-attr="placeholder:login.userid_placeholder" required>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label" data-i18n="login.password_label">Password</label>
                            <input type="password" class="form-control" name="password" id="password"
                                   placeholder="Enter your password" data-i18n-attr="placeholder:login.password_placeholder" required>
                            <div class="text-end mt-1">
                                <a href="password_forgotten.php" class="text-decoration-none small" data-i18n="login.forgot">Forgot password?</a>
                            </div>
                        </div>

                        <div class="mb-4 d-flex justify-content-center">
                            <div class="cf-turnstile"
                                 data-sitekey="<?php echo $_ENV['CLOUDFLARE_TURNSTILE_PUBLIC']; ?>"
                                 data-theme="light"
                                 data-size="flexible">
                            </div>
                        </div>

                        <div class="d-grid mb-3">
                            <button type="submit" name="loginbutton" class="btn btn-primary btn-lg" data-i18n="login.submit">Log In</button>
                        </div>

                        <div class="text-center">
                            <span class="text-muted" data-i18n="login.no_account">Don't have an account?</span>
                            <a href="creation.php" class="text-decoration-none fw-bold" data-i18n="login.signup_link">Sign up</a>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<?php include("./includes/footer.php"); ?>
</body>
</html>
