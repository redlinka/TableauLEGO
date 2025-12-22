<?php
require __DIR__ . '/includes/config.php';
require_once __DIR__ . '/classes/Email.php';  

if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$errors = [];


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();

    $email      = trim($_POST['email'] ?? '');
    $password   = $_POST['password'] ?? '';
    $password2  = $_POST['password_confirm'] ?? '';
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = t('errorlog');
    }
    // Password strength: min 12 chars, 1 lowercase, 1 uppercase, 1 digit
    $strongPattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{12,}$/';
    if (!preg_match($strongPattern, $password)) {
        $errors[] = 'Password must be at least 12 characters and include a lowercase, uppercase, and a number.';
    }
    if ($password !== $password2) {
        $errors[] = t('errpassword2');
    }
    if (!preg_match($strongPattern, $password)) {
        $errors[] = t('errpassword_strong');
    }

    if (!$errors && $pdo) {
        // Verify if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        if ($stmt->fetch()) {
            $errors[] = t('emailerr');
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("
                INSERT INTO users (email, password_hash, first_name, last_name)
                VALUES (:email, :hash, :first_name, :last_name)
                RETURNING id
            ");
            $stmt->execute([
                'email'      => $email,
                'hash'       => $hash,
                'first_name' => $first_name,
                'last_name'  => $last_name,
            ]);

            $userId = $stmt->fetchColumn();

            regenerate_session_id_safe(); // avoid fixation when creating session
            $_SESSION['user_id']    = $userId;
            $_SESSION['user_email'] = $email;
            $_SESSION['is_admin']   = false;
            
            // Welcome email
            $emailService = new EmailService();
            if ($emailService->isAvailable()) {
                $to = $email;
                $subject = t('mail_register_subject');
                
                $htmlBody = t('mail_register_body');
                $htmlBody = str_replace(
                    ['{{email}}'],
                    [htmlspecialchars($email)],
                    $htmlBody
                );

                $emailService->send($to, $subject, $htmlBody);
            }

            header('Location: index.php');
            exit;
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

          <h2 class="h4 fw-bold mb-4 text-center"><?= t('register'); ?></h2>

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
              <label class="form-label"><?= t('firstname'); ?></label>
              <input class="form-control" type="text" name="first_name" required>
            </div>
            <div class="mb-3">
              <label class="form-label"><?= t('lastname'); ?></label>
              <input class="form-control" type="text" name="last_name" required>
            </div>
            <div class="mb-3">
              <label class="form-label"><?= t('mail'); ?></label>
              <input class="form-control" type="email" name="email" required>
            </div>
            <div class="mb-3">
              <label class="form-label"><?= t('password'); ?></label>
              <input class="form-control" type="password" name="password" required>
              <div class="form-text"><?= t('errpassword'); ?></div>
            </div>
            <div class="mb-4">
              <label class="form-label"><?= t('passwordconfirm'); ?></label>
              <input class="form-control" type="password" name="password_confirm" required>
            </div>
            <button type="submit" class="btn btn-primary w-100"><?= t('createacc'); ?></button>
          </form>

          <p class="small text-center mt-3 mb-0">
            <?= t('alraedyacc'); ?>
            <a href="login.php"><?= t('login'); ?></a>
          </p>

        </div>
      </div>
    </div>
  </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
