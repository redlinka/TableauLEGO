<?php
$token = $_GET['token'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Img2Brick</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="assets/css/reset_password.css">
</head>

<body>
  <div class="container">
    <div class="reset">
      <h1>Reset your password</h1>

      <?php if (isset($_GET['error'])): ?>
        <p class="error"><?= htmlspecialchars($_GET['error']) ?></p>
      <?php endif; ?>

      <?php if (isset($_GET['success'])): ?>
        <p class="success"><?= htmlspecialchars($_GET['success']) ?></p>
      <?php endif; ?>

      <?php if (!$token): ?>
        <p>Invalid reset link.</p>
      <?php else: ?>
        <form method="post" action="../src/Service/ResetPassword.php">
          <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
          <input id="new_password" name="new_password" placeholder="New password" type="password" required minlength="12"><br><br>
          <input id="confirm_password" name="confirm_password" placeholder="Confirm password" type="password" required minlength="12"><br><br>
          <div class="buttons">
            <a href="./account.php" class="cancel">Cancel</a>
            <button class="button" type="submit">Set new password</button>
          </div>
        </form>
      <?php endif; ?>
    </div>
  </div>
  <footer>
    <a href="https://github.com/Draken1003/img2brick" target="_blank">GitHub</a>
  </footer>
</body>

</html>