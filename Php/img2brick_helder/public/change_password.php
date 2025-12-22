<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Img2Brick</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/change_password.css">
</head>

<body>
    <div class="bg-circle"></div>
    <div class="container">

        <form action="../src/Service/ChangePassword.php" method="post">
            <h1>Change your password</h1>
            <input type="password" name="current_password" placeholder="Enter your current password" required minlength="12">
            <input type="password" name="new_password" placeholder="Enter your new password" required minlength="12">
            <input type="password" name="confirm_password" placeholder="Confirm your new password" required minlength="12">

            <?php
            if (isset($_GET['error'])) {
                $error = htmlspecialchars($_GET['error']);
                echo '<p class="error">' . $error . '</p>';
            }
            if (isset($_GET['success'])) {
                $error = htmlspecialchars($_GET['success']);
                echo '<p class="valid">' . $error . '</p>';
            }
            ?>

            <div class="buttons">
                <a href="account.php">Cancel</a>
                <input type="submit" value="Confirm">
            </div>
        </form>
    </div>
    <footer>
        <a href="https://github.com/Draken1003/img2brick" target="_blank">GitHub</a>
    </footer>
</body>

</html>