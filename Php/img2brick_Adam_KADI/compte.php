<?php
// Account page
require __DIR__ . '/includes/config.php';
require_once __DIR__ . '/classes/Email.php';

if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    header('Location: login.php');
    exit;
}

$errors = [];
$success = false;
$passwordSuccess = false;

// Get user infos
$stmt = $pdo->prepare("
    SELECT email, first_name, last_name,
           billing_address, billing_zip, billing_city, billing_country,
           phone
    FROM users
    WHERE id = :id
");
$stmt->execute(['id' => $userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    // If user is deleted but session still alive
    $errors[] = "Account not found.";
}

// Form treatment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    require_csrf_token();

    $action = $_POST['action'] ?? 'profile';

    if ($action === 'password') {
        $old = $_POST['old_password'] ?? '';
        $new1 = $_POST['new_password'] ?? '';
        $new2 = $_POST['new_password_confirm'] ?? '';

        if (strlen($new1) < 8) {
            $errors[] = "New password must be at least 8 characters.";
        }
        if ($new1 !== $new2) {
            $errors[] = "Passwords do not match.";
        }

        // Fetch current hash
        $pwdRow = $pdo->prepare("SELECT password_hash, email FROM users WHERE id = :id");
        $pwdRow->execute(['id' => $userId]);
        $pwd = $pwdRow->fetch(PDO::FETCH_ASSOC);

        if (!$pwd || !password_verify($old, $pwd['password_hash'])) {
            $errors[] = "Current password is incorrect.";
        }

        if (!$errors) {
            $newHash = password_hash($new1, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password_hash = :h WHERE id = :id")
                ->execute(['h' => $newHash, 'id' => $userId]);

            $passwordSuccess = true;

            // Notify user by email about the password change
            $emailService = new EmailService();
            if ($emailService->isAvailable()) {
                $emailService->send(
                    $pwd['email'],
                    'Your password was changed',
                    '<p>Your img2brick account password was just updated. If this was not you, please reset it immediately.</p>'
                );
            }
        }
    } else {
        $email           = trim($_POST['email'] ?? '');
        $first_name      = trim($_POST['first_name'] ?? '');
        $last_name       = trim($_POST['last_name'] ?? '');
        $billing_address = trim($_POST['billing_address'] ?? '');
        $billing_zip     = trim($_POST['billing_zip'] ?? '');
        $billing_city    = trim($_POST['billing_city'] ?? '');
        $billing_country = trim($_POST['billing_country'] ?? '');
        $phone           = trim($_POST['phone'] ?? '');

        // Validatiing
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email address.";
        }

        if (!$errors) {
            // Verify if email is not taken already
            $check = $pdo->prepare("SELECT id FROM users WHERE email = :email AND id <> :id");
            $check->execute(['email' => $email, 'id' => $userId]);
            if ($check->fetch()) {
                $errors[] = "This email is already used by another account.";
            } else {
                // Update
                $update = $pdo->prepare("
                    UPDATE users
                    SET email = :email,
                        first_name = :first_name,
                        last_name = :last_name,
                        billing_address = :billing_address,
                        billing_zip = :billing_zip,
                        billing_city = :billing_city,
                        billing_country = :billing_country,
                        phone = :phone
                    WHERE id = :id
                ");

                $update->execute([
                    'email'           => $email,
                    'first_name'      => $first_name,
                    'last_name'       => $last_name,
                    'billing_address' => $billing_address,
                    'billing_zip'     => $billing_zip,
                    'billing_city'    => $billing_city,
                    'billing_country' => $billing_country,
                    'phone'           => $phone,
                    'id'              => $userId,
                ]);

                // Update session
                $_SESSION['user_email'] = $email;

                // Recharger les valeurs pour le formulaire
                $user = [
                    'email'           => $email,
                    'first_name'      => $first_name,
                    'last_name'       => $last_name,
                    'billing_address' => $billing_address,
                    'billing_zip'     => $billing_zip,
                    'billing_city'    => $billing_city,
                    'billing_country' => $billing_country,
                    'phone'           => $phone,
                ];

                $success = true;

                // Notify profile update
                $emailService = new EmailService();
                if ($emailService->isAvailable()) {
                    $emailService->send(
                        $email,
                        'Your profile was updated',
                        '<p>Your img2brick account details were updated.</p>'
                    );
                }
            }
        }
    }
}

include __DIR__ . '/includes/header.php';
?>

<main class="container py-5">
  <div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-6">
      <div class="card shadow-lg border-0">
        <div class="card-body p-4 p-md-5">
          <h2 class="h4 fw-bold mb-4 text-center">
            <?= function_exists('t') ? t('myaccount') : 'My account'; ?>
          </h2>

          <?php if ($success): ?>
            <div class="alert alert-success small">
              <?= function_exists('t') ? t('account_updated') : 'Your account has been updated.'; ?>
            </div>
          <?php endif; ?>
          <?php if ($passwordSuccess): ?>
            <div class="alert alert-success small">
              Password updated successfully.
            </div>
          <?php endif; ?>

          <?php if ($errors): ?>
            <div class="alert alert-danger small">
              <?php foreach ($errors as $e): ?>
                <div><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8'); ?></div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <?php if ($user): ?>
          <form action="" method="post" novalidate>
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="action" value="profile">
            <h3 class="h6 text-uppercase text-muted mb-3">
              <?= function_exists('t') ? t('account_section_identity') : 'Identity'; ?>
            </h3>

            <div class="mb-3">
              <label class="form-label"><?= function_exists('t') ? t('firstname') : 'First name'; ?></label>
              <input
                class="form-control"
                type="text"
                name="first_name"
                value="<?= htmlspecialchars($user['first_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
              >
            </div>

            <div class="mb-3">
              <label class="form-label"><?= function_exists('t') ? t('lastname') : 'Last name'; ?></label>
              <input
                class="form-control"
                type="text"
                name="last_name"
                value="<?= htmlspecialchars($user['last_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
              >
            </div>

            <div class="mb-4">
              <label class="form-label"><?= function_exists('t') ? t('mail') : 'Email'; ?></label>
              <input
                class="form-control"
                type="email"
                name="email"
                required
                value="<?= htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
              >
            </div>

            <h3 class="h6 text-uppercase text-muted mb-3 mt-4">
              <?= function_exists('t') ? t('account_section_billing') : 'Billing address'; ?>
            </h3>

            <div class="mb-3">
              <label class="form-label"><?= function_exists('t') ? t('address') : 'Address'; ?></label>
              <textarea
                class="form-control"
                name="billing_address"
                rows="2"
              ><?= htmlspecialchars($user['billing_address'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>

            <div class="row">
              <div class="col-md-4 mb-3">
                <label class="form-label"><?= function_exists('t') ? t('zip') : 'ZIP'; ?></label>
                <input
                  class="form-control"
                  type="text"
                  name="billing_zip"
                  value="<?= htmlspecialchars($user['billing_zip'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                >
              </div>
              <div class="col-md-8 mb-3">
                <label class="form-label"><?= function_exists('t') ? t('city') : 'City'; ?></label>
                <input
                  class="form-control"
                  type="text"
                  name="billing_city"
                  value="<?= htmlspecialchars($user['billing_city'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                >
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label"><?= function_exists('t') ? t('country') : 'Country'; ?></label>
              <input
                class="form-control"
                type="text"
                name="billing_country"
                value="<?= htmlspecialchars($user['billing_country'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
              >
            </div>

            <div class="mb-4">
              <label class="form-label"><?= function_exists('t') ? t('phone') : 'Phone'; ?></label>
              <input
                class="form-control"
                type="text"
                name="phone"
                value="<?= htmlspecialchars($user['phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
              >
            </div>

            <button type="submit" class="btn btn-primary w-100">
              <?= function_exists('t') ? t('save_changes') : 'Save changes'; ?>
            </button>
          </form>

          <hr class="my-4">

          <h3 class="h6 text-uppercase text-muted mb-3 mt-4">Change password</h3>
          <form action="" method="post" novalidate class="d-flex flex-column gap-3">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="action" value="password">
            <div>
              <label class="form-label">Current password</label>
              <input class="form-control" type="password" name="old_password" required>
            </div>
            <div>
              <label class="form-label">New password</label>
              <input class="form-control" type="password" name="new_password" required>
            </div>
            <div>
              <label class="form-label">Confirm new password</label>
              <input class="form-control" type="password" name="new_password_confirm" required>
            </div>
            <button type="submit" class="btn btn-outline-primary">
              Update password
            </button>
          </form>
          <?php endif; ?>

        </div>
      </div>
    </div>
  </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
