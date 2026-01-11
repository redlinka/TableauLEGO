<?php
session_start();
global $cnx;
include("./config/cnx.php");
require_once __DIR__ . '/includes/i18n.php';

$errors = [];
// States: 'checking' (initial), 'form' (valid token), 'success' (updated), 'error' (invalid token)
$viewState = 'checking';
$message = '';

// 1. TOKEN VERIFICATION
if (!isset($_GET['token'])) {
    $viewState = 'error';
    $message = tr('reset_password.no_token', 'No token provided.');
} else {
    $token = $_GET['token'];

    try {
        $stmt = $cnx->prepare("SELECT * FROM `2FA` WHERE verification_token = ? LIMIT 1");
        $stmt->execute([$token]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) {
            $viewState = 'error';
            $message = tr('reset_password.invalid_token', 'Invalid or expired token.');
        } else {
            $now = new DateTime();
            $expiry = new DateTime($result['token_expire_at']);

            if ($now > $expiry) {
                $viewState = 'error';
                $message = tr('reset_password.expired', 'This link has expired. Please request a new one.');
            } else {
                // Token is valid, show the form
                $viewState = 'form';
                $userId = $result['user_id'];
            }
        }
    } catch (PDOException $e) {
        $viewState = 'error';
        $message = tr('reset_password.db_error', 'Database error. Please try again later.');
    }
}

// 2. FORM SUBMISSION (Only process if token was valid)
if ($viewState === 'form' && $_SERVER["REQUEST_METHOD"] === "POST") {

    if (!csrf_validate($_POST['csrf'] ?? null)) {
        $errors[] = 'Invalid session.';
    } else {
        $password = $_POST['password'];

        // must be identical to creation.php
        // Enforce password complexity
        if (strlen($password) < 12) {
            $errors[] = 'Password must be at least 12 characters long';
        }
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter';
        }
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter';
        }
        if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
            $errors[] = 'Password must contain at least one special character';
        }

        if (empty($errors)) {
            $newPassword = password_hash($password, $_ENV['ALGO']);

            try {
                $cnx->beginTransaction();
                $stmt = $cnx->prepare("UPDATE USER SET password = ? WHERE user_id = ?");
                $stmt->execute([$newPassword, $userId]);

                $stmt = $cnx->prepare("DELETE FROM `2FA` WHERE verification_token = ?");
                $stmt->execute([$token]);

                unset($_SESSION['last_password_reset_sent']);
                $cnx->commit();

                // Switch to success view
                $viewState = 'success';
                csrf_rotate();
                addLog($cnx, "USER", "RESET", "password");
            } catch (Exception $e) {
                if ($cnx->inTransaction()) $cnx->rollBack();
                $errors[] = 'Database error. Please try again later.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(tr('reset_password.page_title', 'Reset Password')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .req-item {
            margin-bottom: 2px;
            font-size: 0.85rem;
        }

        .invalid {
            color: #dc3545;
        }

        .success {
            color: #198754;
        }

        .icon-box {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .text-danger-custom {
            color: #dc3545;
        }
    </style>
</head>

<body class="bg-light d-flex flex-column min-vh-100">

    <?php include("./includes/navbar.php"); ?>

    <div class="container flex-grow-1 d-flex align-items-center justify-content-center py-5">
        <div class="row justify-content-center w-100">
            <div class="col-md-6 col-lg-5">

                <?php if ($viewState === 'success'): ?>
                    <div class="card shadow-sm border-0 text-center">
                        <div class="card-body p-5">
                            <div class="icon-box text-success">OK</div>
                            <h2 class="fw-bold mb-3" data-i18n="reset_password.success_title">Password Updated!</h2>
                            <p class="text-muted mb-4" data-i18n="reset_password.success_text">Your password has been securely reset. You can now log in with your new credentials.</p>
                            <div class="d-grid">
                                <a href="connexion.php" class="btn btn-primary btn-lg" data-i18n="reset_password.login_now">Log In Now</a>
                            </div>
                        </div>
                    </div>

                <?php elseif ($viewState === 'error'): ?>
                    <div class="card shadow-sm border-0 text-center">
                        <div class="card-body p-5">
                            <div class="icon-box text-danger-custom">X</div>
                            <h2 class="fw-bold mb-3" data-i18n="reset_password.error_title">Link Expired or Invalid</h2>
                            <p class="text-muted mb-4"><?= htmlspecialchars($message) ?></p>
                            <div class="d-grid gap-2">
                                <a href="password_forgotten.php" class="btn btn-primary" data-i18n="reset_password.request_new">Request New Link</a>
                                <a href="index.php" class="btn btn-outline-secondary" data-i18n="reset_password.go_home">Go Home</a>
                            </div>
                        </div>
                    </div>

                <?php elseif ($viewState === 'form'): ?>
                    <div class="card shadow-sm border-0">
                        <div class="card-body p-4">
                            <h2 class="text-center fw-bold mb-4" data-i18n="reset_password.form_title">Reset Password</h2>

                            <?php if (!empty($errors)): ?>
                                <div class="alert alert-danger">
                                    <ul class="mb-0 ps-3">
                                        <?php foreach ($errors as $error): ?>
                                            <li><?= htmlspecialchars($error) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>

                            <form action="" method="POST">
                                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_get(), ENT_QUOTES, 'UTF-8') ?>">

                                <div class="mb-3">
                                    <label for="password" class="form-label" data-i18n="reset_password.new_password_label">New Password</label>
                                    <input type="password" class="form-control" name="password" id="password"
                                        placeholder="Enter new strong password" data-i18n-attr="placeholder:reset_password.new_password_placeholder" required>
                                </div>

                                <div id="message" class="alert alert-light border small mb-4">
                                    <h6 class="fw-bold mb-2" data-i18n="reset_password.requirements_title">Password must contain:</h6>
                                    <div id="letter" class="req-item invalid" data-i18n="signup.requirements.lowercase">Lowercase letter</div>
                                    <div id="capital" class="req-item invalid" data-i18n="signup.requirements.uppercase">Uppercase letter</div>
                                    <div id="number" class="req-item invalid" data-i18n="signup.requirements.number">Number</div>
                                    <div id="special" class="req-item invalid" data-i18n="signup.requirements.special">Special character</div>
                                    <div id="length" class="req-item invalid" data-i18n="signup.requirements.length">Min 12 characters</div>
                                </div>

                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary btn-lg" data-i18n="reset_password.submit">Update Password</button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>

    <?php include("./includes/footer.php"); ?>

    <script>
        function t(key, fallback) {
            if (window.I18N && typeof window.I18N.t === 'function') {
                return window.I18N.t(key, fallback);
            }
            return fallback || key;
        }

        const myInput = document.getElementById("password");
        if (myInput) {
            const letter = document.getElementById("letter");
            const capital = document.getElementById("capital");
            const number = document.getElementById("number");
            const length = document.getElementById("length");
            const special = document.getElementById("special");

            myInput.onkeyup = function() {
                const okPrefix = t('common.ok_prefix', 'OK ');
                const noPrefix = t('common.no_prefix', 'X ');

                var lowerCaseLetters = /[a-z]/g;
                if (myInput.value.match(lowerCaseLetters)) {
                    letter.classList.remove("invalid");
                    letter.classList.add("success");
                    letter.innerHTML = okPrefix + t('signup.requirements.lowercase', 'Lowercase letter');
                } else {
                    letter.classList.remove("success");
                    letter.classList.add("invalid");
                    letter.innerHTML = noPrefix + t('signup.requirements.lowercase', 'Lowercase letter');
                }

                var upperCaseLetters = /[A-Z]/g;
                if (myInput.value.match(upperCaseLetters)) {
                    capital.classList.remove("invalid");
                    capital.classList.add("success");
                    capital.innerHTML = okPrefix + t('signup.requirements.uppercase', 'Uppercase letter');
                } else {
                    capital.classList.remove("success");
                    capital.classList.add("invalid");
                    capital.innerHTML = noPrefix + t('signup.requirements.uppercase', 'Uppercase letter');
                }

                var numbers = /[0-9]/g;
                if (myInput.value.match(numbers)) {
                    number.classList.remove("invalid");
                    number.classList.add("success");
                    number.innerHTML = okPrefix + t('signup.requirements.number', 'Number');
                } else {
                    number.classList.remove("success");
                    number.classList.add("invalid");
                    number.innerHTML = noPrefix + t('signup.requirements.number', 'Number');
                }

                if (myInput.value.length >= 12) {
                    length.classList.remove("invalid");
                    length.classList.add("success");
                    length.innerHTML = okPrefix + t('signup.requirements.length', 'Min 12 characters');
                } else {
                    length.classList.remove("success");
                    length.classList.add("invalid");
                    length.innerHTML = noPrefix + t('signup.requirements.length', 'Min 12 characters');
                }

                var specials = /[!@#$%^&*(),.?":{}|<>]/g;
                if (myInput.value.match(specials)) {
                    special.classList.remove("invalid");
                    special.classList.add("success");
                    special.innerHTML = okPrefix + t('signup.requirements.special', 'Special character');
                } else {
                    special.classList.remove("success");
                    special.classList.add("invalid");
                    special.innerHTML = noPrefix + t('signup.requirements.special', 'Special character');
                }
            }
        }
    </script>
</body>

</html>