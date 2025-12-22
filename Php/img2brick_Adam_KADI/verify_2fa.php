<?php
require_once __DIR__ . '/includes/config.php';

if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$errors = [];
$email = $_SESSION['2fa_pending_email'] ?? '';
$expiresAt = $_SESSION['2fa_expires_at'] ?? time();
$remaining = max(0, $expiresAt - time());


if (!isset($_SESSION['2fa_pending_user_id'], $_SESSION['2fa_code'], $_SESSION['2fa_expires_at'])) {
    header('Location: login.php?error=' . urlencode(t('2fa_missing')));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();

    $input = trim($_POST['code'] ?? '');

    // Expiring
    if (time() > (int)$_SESSION['2fa_expires_at']) {
        $errors[] = t('2fa_expired');
    } else {
        $_SESSION['2fa_attempts'] = ($_SESSION['2fa_attempts'] ?? 0) + 1;

        // Limited tries (set to 5)
        if ($_SESSION['2fa_attempts'] > 5) {
            $errors[] = t('2fa_toomany');
        } elseif (!hash_equals((string)$_SESSION['2fa_code'], $input)) {
            $errors[] = t('2fa_invalid');
        } else {
            // 2FA OK => connects
            regenerate_session_id_safe(); // ensure fresh session id after full auth
            $_SESSION['user_id']    = (int)$_SESSION['2fa_pending_user_id'];
            $_SESSION['user_email'] = (string)($_SESSION['2fa_pending_email'] ?? '');

            // Cleaning session
            unset(
                $_SESSION['2fa_pending_user_id'],
                $_SESSION['2fa_pending_email'],
                $_SESSION['2fa_code'],
                $_SESSION['2fa_expires_at'],
                $_SESSION['2fa_attempts']
            );

            log_event((int)$_SESSION['user_id'], 'login', 'success', '2FA verified');

            header('Location: index.php');
            exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $errors) {
    log_event((int)($_SESSION['2fa_pending_user_id'] ?? 0), '2fa', 'verify_fail', 'Invalid/expired 2FA code');
}


include __DIR__ . '/includes/header.php';
?>

<main class="container py-5">
  <div class="row justify-content-center">
    <div class="col-12 col-md-6 col-lg-5">
      <div class="card shadow-lg border-0">
        <div class="card-body p-4 p-md-5">
          <h2 class="h4 fw-bold mb-2 text-center"><?= t('2fa_title'); ?></h2>
          <p class="text-secondary small text-center mb-4">
            <?= t('2fa_subtitle'); ?>
            <br>
            <strong><?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?></strong>
          </p>

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
              <label class="form-label"><?= t('2fa_code_label'); ?></label>
              <input class="form-control" type="text" name="code" inputmode="numeric" maxlength="6" required>
              <div class="form-text"><?= t('2fa_hint'); ?></div>
            </div>

            <button type="submit" class="btn btn-primary w-100">
              <?= t('2fa_verify_btn'); ?>
            </button>
          </form>

          <div class="text-center mt-3">
            <a class="small text-decoration-none" href="login.php">
              <?= t('2fa_back_login'); ?>
            </a>
          </div>
	  <div class="text-center mt-3">

  <p class="small text-muted mb-2">
    <?= t('2fa_timer_label'); ?>
    <strong>
      <span id="timer"><?= $remaining ?></span>s
    </strong>
  </p>

  <form method="post" action="resend_2fa.php">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
    <button
      type="submit"
      id="resendBtn"
      class="btn btn-link p-0 small text-decoration-none"
      <?= $remaining > 0 ? 'disabled' : '' ?>
    >
      <?= t('2fa_resend'); ?>
    </button>
  </form>

</div>
<script>
// Timer
(function () {
  let remaining = parseInt(document.getElementById('timer').textContent, 10);
  const timerEl = document.getElementById('timer');
  const resendBtn = document.getElementById('resendBtn');

  if (isNaN(remaining) || remaining <= 0) {
    if (resendBtn) resendBtn.disabled = false;
    return;
  }

  const interval = setInterval(() => {
    remaining--;

    if (remaining <= 0) {
      clearInterval(interval);
      timerEl.textContent = '0';
      if (resendBtn) resendBtn.disabled = false;
    } else {
      timerEl.textContent = remaining.toString();
    }
  }, 1000);
})();
</script>



        </div>
      </div>
    </div>
  </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
