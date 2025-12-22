<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/classes/Email.php';

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();

    $email = trim($_POST['email'] ?? '');

    // Generic message (anti-leak)
    $success = true;

    if (filter_var($email, FILTER_VALIDATE_EMAIL) && $pdo) {
        // Searching user
        $stmt = $pdo->prepare("SELECT id, email FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Invalidate old tokens
            $pdo->prepare("UPDATE password_resets SET used_at = NOW() WHERE user_id = :uid AND used_at IS NULL")
                ->execute(['uid' => (int)$user['id']]);

            // Generate token + hash
            $token = bin2hex(random_bytes(32)); // 64 chars
            $tokenHash = hash('sha256', $token);

            // Expiring in 15 minutes
            $expiresAt = (new DateTime('+15 minutes'))->format('Y-m-d H:i:s');

            // Stock in database
            $ins = $pdo->prepare("
                INSERT INTO password_resets (user_id, token_hash, expires_at)
                VALUES (:uid, :th, :exp)
            ");
            $ins->execute([
                'uid' => (int)$user['id'],
                'th'  => $tokenHash,
                'exp' => $expiresAt
            ]);

            // Build link if token exists
            $link = rtrim(BASE_URL, '/') . '/reset_password.php?token=' . urlencode($token);

            // Send mail if email exists
            $emailService = new EmailService();
            if ($emailService->isAvailable()) {
                $subject = t('reset_subject');
                $body = t('reset_body');
                $body = str_replace(['{{link}}', '{{minutes}}'], [$link, '15'], $body);

                $emailService->send($user['email'], $subject, $body);

                // log_event((int)$user['id'], 'reset_password', 'request', 'Password reset requested');
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
          <h2 class="h4 fw-bold mb-3 text-center"><?= t('forgot_title'); ?></h2>
          <p class="text-secondary small text-center mb-4"><?= t('forgot_subtitle'); ?></p>

          <?php if ($success): ?>
            <div class="alert alert-success small">
              <?= t('forgot_success'); ?>
            </div>
          <?php endif; ?>

          <?php if ($errors): ?>
            <div class="alert alert-danger small">
              <?php foreach ($errors as $e): ?>
                <div><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8'); ?></div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <form method="post" novalidate>
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
            <div class="mb-3">
              <label class="form-label"><?= t('mail'); ?></label>
              <input class="form-control" type="email" name="email" required>
            </div>

            <button type="submit" class="btn btn-primary w-100">
              <?= t('forgot_btn'); ?>
            </button>
          </form>

          <div class="text-center mt-3">
            <a class="small text-decoration-none" href="login.php"><?= t('back_login'); ?></a>
          </div>

        </div>
      </div>
    </div>
  </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
