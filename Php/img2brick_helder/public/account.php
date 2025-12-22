<?php

require_once "../src/Model/User.php";
session_start();

if (!isset($_SESSION['user_obj'])) {
    header("Location: sign_in.php");
    exit;
}

$user = $_SESSION['user_obj'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Img2Brick</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/account.css">

</head>

<body>
    <div class="bg-circle"></div>
    <header class="c-nav">
        <h2>Img2Brick</h2>
        <nav>
            <ul>
                <li><a href="index.php">Home</a></li>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li><a href="my_orders.php">My Orders</a></li>
                <?php endif; ?>
            </ul>
        </nav>
        <div class="right">
            <?php
            if (isset($_SESSION['user_id'])) {
                echo '<a href="" class="profil"><img width="30" height="30" src="https://img.icons8.com/?size=100&id=85147&format=png&color=ffffff" /></a>';
            } else {
                echo '<a href="sign-in.php" class="sign-in">Sign in</a>';
            }
            ?>
        </div>
    </header>

    <div class="container">
        <div class="c-container">
            <h1>My account</h1>

            <!-- Personal information -->
            <div class="info-section">
                <form action="../src/Service/UpdateUser.php" method="POST">
                    <div class="names">
                        <div>
                            <label for="first_name">
                                FirstName
                                <input type="text" id="first_name" name="first_name" value="<?= htmlspecialchars($user->first_name) ?>" required>
                            </label>
                        </div>
                        <div>
                            <label for="last_name">
                                LastName
                                <input type="text" id="last_name" name="last_name" value="<?= htmlspecialchars($user->last_name) ?>" required>
                            </label>
                        </div>
                    </div>

                    <label for="email">
                        Email
                        <input type="email" id="email" name="email" value="<?= htmlspecialchars($user->email) ?>" required>
                    </label>

                    <label for="phone">
                        Phone
                        <input type="tel" id="phone" name="phone" pattern="[0-9]{10}" minlength="10" maxlength="10" value="<?= htmlspecialchars($user->phone ?? '') ?>">
                    </label>

                    <label for="address">
                        Default Address
                        <input type="text" id="address" name="address" value="<?= htmlspecialchars($user->address ?? '') ?>">
                    </label>

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
                    <button type="submit" class="btn">Save Changes</button>
                </form>
            </div>

            <div class="info-section password-section">
                <a href="change_password.php" class="btn">Change my password</a>
                <a href="../src/Service/ForgotPassword.php" class="btn">Recover my password</a>
            </div>

            <a href="../src/Service/Logout.php" class="btn logout">Log out</a>
        </div>
    </div>
    <footer>
        <a href="https://github.com/Draken1003/img2brick" target="_blank">GitHub</a>
    </footer>

    <script>
        document.getElementById("phone").addEventListener("keypress", function(event) {
            var key = event.keyCode;
            // Only allow numbers to be entered
            if (key < 48 || key > 57) {
                event.preventDefault();
            }
        });
    </script>
</body>

</html>