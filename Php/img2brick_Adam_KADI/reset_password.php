<?php
require_once __DIR__ . '/includes/config.php';

$errors = [];
$success = false;

$token = isset($_GET['token']) ? trim((string)$_GET['token']) : '';
$valid = false;
$resetRow = null;

if ($token !== '' && $pdo) {
    $tokenHash = hash('sha256', $token);

    $stmt = $pdo->prepare("
        SELECT id, user_id, expires_at, used_at
        FROM password_resets
        WHERE token_hash = :th
        LIMIT 1
    ");
    $stmt->execute(['th' => $tokenHash]);
    $resetRow = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($resetRow) {
        $expired = (new DateTime() > new DateTime($resetRow['expires_at']));
        $used = !empty($resetRow['used_at']);
        $valid = !$expired && !$used;
    }
}

// POST : update password
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();

    $tokenPost = trim((string)($_POST['token'] ?? ''));
    $pass1 = (string)($_POST['password'] ?? '');
    $pass2 = (string)($_POST['password_confirm'] ?? '');

    if ($tokenPost === '' || !$pdo) {
        $errors[] = t('reset_invalid');
    } else {
        $tokenHash = hash('sha256', $tokenPost);

        $stmt = $pdo->prepare("
            SELECT id, user_id, expires_at, used_at
            FROM password_resets
            WHERE token_hash = :th
            LIMIT 1
        ");
        $stmt->execute(['th' => $tokenHash]);
        $resetRow = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$resetRow) {
            $errors[] = t('reset_invalid');
        } else {
            $expired = (new DateTime() > new DateTime($resetRow['expires_at']));
            $used = !empty($resetRow['used_at']);
            if ($expired || $used) {
                $errors[] = t('reset_invalid');
            }
        }
    }

    if (strlen($pass1) < 8) {
        $errors[] = t('errpassword');
    }
    if ($pass1 !== $pass2) {
        $errors[] = t('errpassword2');
    }

    if (!$errors && $resetRow) {
        $hash = password_hash($pass1, PASSWORD_DEFAULT);

        $pdo->prepare("UPDATE users SET password_hash = :h WHERE id = :uid")
            ->execute(['h' => $hash, 'uid' => (int)$resetRow['user_id']]);

        $pdo->prepare("UPDATE password_resets SET used_at = NOW() WHERE id = :id")
            ->execute(['id' => (int)$resetRow['id']]);

        $success = true;
        $valid = false; // cannot reuse form with refresh
    }
}

include __DIR__ . '/includes/header.php';
?>
<main class="container py-5">
  <div class="row justify-content-center">
    <div class="col-12 col-md-6 col-lg-5">
      <div class="card shadow-lg border-0">
        <div class="card-body p-4 p-md-5">

          <h2 class="h4 fw-bold mb-3 text-center"><?= t('reset_title'); ?></h2>

          <?php if ($success): ?>
            <div class="alert alert-success small">
              <?= t('reset_success'); ?>
              <div class="mt-2">
                <a href="login.php" class="btn btn-primary btn-sm"><?= t('login'); ?></a>
              </div>
            </div>
          <?php else: ?>

            <?php if (!$valid && !$errors): ?>
              <div class="alert alert-danger small">
                <?= t('reset_invalid'); ?>
              </div>
            <?php endif; ?>

            <?php if ($errors): ?>
              <div class="alert alert-danger small">
                <?php foreach ($errors as $e): ?>
                  <div><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <?php if ($valid): ?>
              <form method="post" novalidate>
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">

                <div class="mb-3">
                  <label class="form-label"><?= t('reset_new_password'); ?></label>
                  <input class="form-control" type="password" name="password" required>
                </div>

                <div class="mb-4">
                  <label class="form-label"><?= t('reset_confirm_password'); ?></label>
                  <input class="form-control" type="password" name="password_confirm" required>
                </div>

                <button type="submit" class="btn btn-primary w-100">
                  <?= t('reset_btn'); ?>
                </button>
              </form>
            <?php endif; ?>

          <?php endif; ?>

        </div>
      </div>
    </div>
  </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
