<?php
require_once __DIR__ . '/config.php';

// Display variables 
$isLogged  = is_logged_in();
$userEmail = $_SESSION['user_email'] ?? '';
$lang      = $_SESSION['lang'] ?? 'en';
?>
<!doctype html>
<html lang="<?= htmlspecialchars($lang, ENT_QUOTES, 'UTF-8'); ?>">
<head>
  <meta charset="utf-8">
  <title>img2brick - <?= t('title'); ?> - by Adam KADI</title>
  <link rel="icon" type="image/x-icon" href="/lego-brick-png-32.ico">
  <meta name="viewport" content="width=device-width, initial-scale=1">


  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="assets/styles.css">

  <script src="assets/upload.js" defer></script>

</head>

<body class="bg-dots">
  <header class="border-bottom bg-white">
    <div class="container d-flex align-items-center justify-content-between py-3">

      <!-- Logo -->
      <a class="navbar-brand d-inline-flex align-items-center gap-2 fw-bold text-decoration-none" href="index.php">
        <span class="logo-dot"></span>
        <span class="text-dark">img2brick</span>
      </a>

      <!-- Navigation -->
      <nav class="d-flex gap-3 small align-items-center">

        <?php if ($isLogged): ?>

          <!-- My orders -->
          <a class="link-secondary text-decoration-none" href="commandes.php">
            <?= t('myorders'); ?>
          </a>

          <!-- My account -->
          <a class="link-secondary text-decoration-none" href="compte.php">
            <?= function_exists('t') ? t('myaccount') : 'My account'; ?>
          </a>

          <!-- Welcome -->
          <span>
            <?= t('welcome'); ?>
            <?= htmlspecialchars($userEmail, ENT_QUOTES, 'UTF-8'); ?>
          </span>

          <!-- Log out -->
          <a class="link-secondary text-decoration-none" href="logout.php">
            <?= t('logout'); ?>
          </a>

        <?php else: ?>

          <!-- Log in / register -->
          <a class="link-secondary text-decoration-none" href="login.php">
            <?= t('btn_login'); ?>
          </a>

          <a class="link-secondary text-decoration-none" href="register.php">
            <?= t('btn_register'); ?>
          </a>

        <?php endif; ?>

        <!-- Language change -->
        <div class="border-start ps-3 ms-2 d-flex gap-2">
          <a class="link-secondary text-decoration-none fw-bold" href="?lang=en">EN</a>
          <a class="link-secondary text-decoration-none fw-bold" href="?lang=fr">FR</a>
        </div>

      </nav>

    </div>
  </header>
