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
            <h1>Enter your email</h1>

            <?php if (isset($_GET['error'])): ?>
                <p class="error"><?= htmlspecialchars($_GET['error']) ?></p>
            <?php endif; ?>

            <?php if (isset($_GET['success'])): ?>
                <p class="valid"><?= htmlspecialchars($_GET['success']) ?></p>
            <?php endif; ?>

            <form method="post" action="../src/Service/CheckEmail.php">
                <input id="email" name="email" placeholder="Email" type="email" required><br><br>
                <div class="buttons">
                    <a href="./sign_in.php" class="cancel">Cancel</a>
                    <button class="button" type="submit">Send</button>
                </div>
            </form>

        </div>
    </div>
    <footer>
        <a href="https://github.com/Draken1003/img2brick" target="_blank">GitHub</a>
    </footer>
</body>

</html>