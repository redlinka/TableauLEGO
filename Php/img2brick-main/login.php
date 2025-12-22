<?php
require __DIR__ . '/includes/config.php';
require_once __DIR__ . '/classes/Email.php';

if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();

    $maxAttempts = 5;
    $lockSeconds = 60;
    $now = time();

    if (!empty($_SESSION['login_lock_until']) && (int)$_SESSION['login_lock_until'] > $now) {
        $errors[] = t('login_toomany');
    } else {
        if (!empty($_SESSION['login_lock_until'])) {
            unset($_SESSION['login_lock_until'], $_SESSION['login_attempts']);
        }

    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = t('errorlog');
    }

    if (!$errors && $pdo) {
        // Getting email too
        $stmt = $pdo->prepare("SELECT id, email, password_hash, is_admin FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            unset($_SESSION['login_attempts'], $_SESSION['login_lock_until']);
            regenerate_session_id_safe(); // stop session fixation when auth succeeds

            // 2FA code generation
            $code = (string)random_int(100000, 999999);

            $_SESSION['2fa_pending_user_id'] = (int)$user['id'];
            $_SESSION['2fa_pending_email']   = (string)$user['email'];
            $_SESSION['2fa_code']            = $code;
            $_SESSION['2fa_expires_at']      = time() + 60; // 1 minute
            $_SESSION['2fa_attempts']        = 0;

            // Email sending
            $emailService = new EmailService();
            if ($emailService->isAvailable()) {
                $to = (string)$user['email'];
                $subject = t('2fa_subject');

                $body = t('2fa_body');
                $body = str_replace(['{{code}}', '{{minutes}}'], [$code, '1'], $body);

                // Sending only if email is valid
                if (filter_var($to, FILTER_VALIDATE_EMAIL)) {
                    $emailService->send($to, $subject, $body);
                }
            }
            log_event((int)$user['id'], 'login', '2fa_challenge', 'Credentials ok, 2FA required', ['email' => $user['email']]);
            log_event((int)$user['id'], '2fa', 'send', '2FA code sent', ['email' => $user['email']]);

            header('Location: verify_2fa.php');
            exit;

        } else {
            $_SESSION['login_attempts'] = (int)($_SESSION['login_attempts'] ?? 0) + 1;
            $attempts = (int)$_SESSION['login_attempts'];
            $remaining = max(0, $maxAttempts - $attempts);
            if ($attempts >= $maxAttempts) {
                $_SESSION['login_lock_until'] = $now + $lockSeconds;
                $errors[] = t('login_toomany');
            } else {
                $errors[] = t('error');
                if ($remaining > 0 && $remaining <= 3) {
                    $errors[] = str_replace('{{count}}', (string)$remaining, t('login_tries_left'));
                }
            }
            log_event(null, 'login', 'fail', 'Invalid credentials', ['email' => $email]);

        }
    }
    }
}

include __DIR__ . '/includes/header.php';
?>

<main class="container py-5">
  <div class="row justify-content-center">
    <div class="col-12 col-md-6 col-lg-5">
      <div class="card shadow-lg border-0">
        <div class="card-body p-4 p-md-5">

          <h2 class="h4 fw-bold mb-4 text-center"><?= t('connexion'); ?></h2>

          <?php if ($errors): ?>
            <div class="alert alert-danger small">
              <?php foreach ($errors as $e): ?>
                <div><?php echo htmlspecialchars($e, ENT_QUOTES, 'UTF-8'); ?></div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <form action="" method="post" novalidate>
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
            <div class="mb-3">
              <label class="form-label"><?= t('mail'); ?></label>
              <input class="form-control" type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            </div>

            <div class="mb-3">
              <label class="form-label"><?= t('password'); ?></label>
              <input class="form-control" type="password" name="password" required>
            </div>
<div class="text-center mt-3">
  <a class="small text-decoration-none" href="forgot_password.php"><?= t('forgot_title'); ?></a>
</div>


            <button type="submit" class="btn btn-primary w-100"><?= t('login'); ?></button>
          </form>

          <p class="small text-center mt-3 mb-0">
            <?= t('noacc'); ?>
            <a href="register.php"><?= t('register'); ?></a>
          </p>

        </div>
      </div>
    </div>
  </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
