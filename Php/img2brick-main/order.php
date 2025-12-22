<?php
require_once __DIR__ . '/includes/config.php';

if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

include __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/captcha.php';
if (!isset($_SESSION['uploaded_image'])) { header('Location: index.php?error=' . urlencode(t('uploaderr'))); exit; }


if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_csrf_token();
}

$variant = isset($_POST['variant']) ? $_POST['variant'] : null;
$board   = isset($_POST['board_size']) ? $_POST['board_size'] : '64x64';
if (!$variant) { header('Location: results.php'); exit; }
$_SESSION['variant'] = $variant;
$_SESSION['board'] = $board;
list($capA, $capB) = captcha_generate();
log_event((int)$_SESSION['user_id'], 'order', 'checkout_open', 'Checkout page opened', [
  'board' => $board,
  'variant' => $variant
]);

?>
<main class="container py-5">
  <h2 class="h4 fw-bold mb-4"><?= t('check'); ?></h2>
  <form action="confirmation.php" method="post">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
    <div class="row g-4">
      <div class="col-12 col-lg-6">
        <div class="card shadow-sm">
          <div class="card-body">
            <h3 class="h5"><?= t('acc'); ?></h3>
            <div class="row g-3 mt-1">
              <div class="col-12 col-md-6">
                <label class="form-label"><?= t('firstname'); ?></label>
                <input class="form-control" name="first_name" required>
              </div>
              <div class="col-12 col-md-6">
                <label class="form-label"><?= t('lastname'); ?></label>
                <input class="form-control" name="last_name" required>
              </div>
              <div class="col-12">
                <label class="form-label"><?= t('mail'); ?></label>
                <input class="form-control" type="email" name="email" required>
              </div>
              <div class="col-12">
                <label class="form-label"><?= t('password'); ?></label>
                <input class="form-control" type="password" name="password" required>
                <div class="form-text"><?= t('secure'); ?></div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-12 col-lg-6">
        <div class="card shadow-sm">
          <div class="card-body">
            <h3 class="h5"><?= t('address'); ?></h3>
            <div class="row g-3 mt-1">
              <div class="col-12">
                <label class="form-label"><?= t('address'); ?></label>
                <input class="form-control" name="address" required>
              </div>
              <div class="col-6">
                <label class="form-label"><?= t('zip'); ?></label>
                <input class="form-control" name="zip" required>
              </div>
              <div class="col-6">
                <label class="form-label"><?= t('city'); ?></label>
                <input class="form-control" name="city" required>
              </div>
              <div class="col-12">
                <label class="form-label"><?= t('country'); ?></label>
                <input class="form-control" name="country" required>
              </div>
              <div class="col-12">
                <label class="form-label"><?= t('phone'); ?></label>
                <input class="form-control" name="phone" required>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-12">
        <div class="card shadow-sm">
          <div class="card-body">
            <h3 class="h5"><?= t('payment'); ?></h3>
            <div class="row g-3 mt-1">
              <div class="col-12 col-md-6">
                <label class="form-label"><?= t('cardnumber'); ?></label>
                <input class="form-control" name="card" value="4242 4242 4242 4242" required>
              </div>
              <div class="col-6 col-md-3">
                <label class="form-label"><?= t('expiry'); ?></label>
                <input class="form-control" name="expiry" value="12/34" required>
              </div>
              <div class="col-6 col-md-3">
                <label class="form-label"><?= t('cvc'); ?></label>
                <input class="form-control" name="cvc" value="123" required>
              </div>
            </div>
            <div class="small text-secondary mt-2"><?= t('paysim'); ?></div>
          </div>
        </div>
      </div>

      <div class="col-12 mt-3">
        <div class="card shadow-sm">
          <div class="card-body">
            <h3 class="h6"><?= t('captcha'); ?></h3>
            <div class="row g-3 mt-1">
              <div class="col-12 col-md-6">
                <label class="form-label"><?= t('question'); ?> <?php echo $capA; ?> + <?php echo $capB; ?> ?</label>
                <input class="form-control" name="captcha_answer" required>
                <input class="form-control visually-hidden" name="website" autocomplete="off" tabindex="-1" aria-hidden="true">
                <div class="form-text"><?= t('captchaverif'); ?></div>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div>
    <div class="d-flex gap-3 mt-4">
      <a href="results.php" class="btn btn-outline-secondary"><?= t('return'); ?></a>
      <button type="submit" class="btn btn-primary"><?= t('confirmation'); ?></button>
    </div>
  </form>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
