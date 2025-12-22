<?php

require_once '../connexion.inc.php';
require_once '../src/Model/Order.php';
require_once '../src/Model/User.php';
session_start();

if (!isset($_SESSION['image_id'])) {
    header("Location: ./index.php?error=" . urlencode("No image selected"));
}
//unset($_SESSION['user_id']);

if (isset($_POST['mosaic'])) {
    $palletteChoice = $_POST['mosaic'];
    $_SESSION['order_obj']->palette_choice = $palletteChoice;
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Image Generate - Img2Brick</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="./assets/css/order.css">
    <link rel="stylesheet" href="./assets/css/sign.css">
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
            if (isset($_SESSION['user_id'])) { //if already connected
                echo '<a href="account.php" class="profil"><img width="30" height="30" src="https://img.icons8.com/?size=100&id=85147&format=png&color=ffffff" /></a>';
            } else {
                echo '<a href="sign-in.php" class="sign-in">Sign in</a>';
            }
            ?>
        </div>
    </header>


    <div class="container">

        <?php if (!isset($_SESSION['user_id'])): ?>
            <div class="left">
                <!-- Form sign in -->
                <form id="signInForm" class="sign active" action="../src/Service/SignIn.php" method="post">
                    <h1>Sign in</h1><br>
                    <p>You must be logged in or registered to continue.</p> <br>
                    <div class="sign-form">
                        <input type="email" class="input-text" id="email" name="email" placeholder="Email" required>
                        <input type="password" class="input-text" id="password" name="password" placeholder="Password" minlength="12" required>
                        <p>No account ? <a class="toggle" id="toSignUp">Sign up</a></p>
                        <p>Forgotten password ? <a class="toggle" href="./recover_form.php">Recover pass.</a></p>
                        <div class="frc-captcha" style="border-radius:10px; margin-top:10px" data-sitekey="<?= getenv('CAPTCHA_SITEKEY') ?>"></div>

                        <?php if (isset($_GET['error_in'])): ?>
                            <p class="error"><?= htmlspecialchars($_GET['error_in']) ?></p>
                        <?php endif; ?>

                        <?php if (isset($_GET['success_up'])): ?>
                            <p class="valid"><?= htmlspecialchars($_GET['success_up']) ?></p>
                        <?php endif; ?>

                        <br>
                        <input class="confirm" type="submit" value="Sign in">
                    </div>
                </form>

                <!-- Form sign up -->
                <form id="signUpForm" action="../src/Service/SignUp.php" class="sign" method="post">
                    <h1>Sign up</h1><br>
                    <div class="sign-form">
                        <div class="name">
                            <input type="text" class="input-text" id="lastName" name="lastName" placeholder="Last Name" required>
                            <input type="text" class="input-text" id="firstName" name="firstName" placeholder="First Name" required>
                        </div>
                        <input type="text" class="input-text" id="address" name="address" placeholder="Address" required>
                        <input type="email" class="input-text" id="email" name="email" placeholder="Email" required>
                        <input type="password" class="input-text" id="password" name="password" minlength="12" placeholder="Password" required>
                        <p>Already have an account ? <a class="toggle" id="toSignIn">Sign in</a></p>
                        <div class="frc-captcha" style="border-radius:10px; margin-top:10px" data-sitekey="<?= getenv('CAPTCHA_SITEKEY') ?>"></div>

                        <?php if (isset($_GET['error_up'])): ?>
                            <p class="error"><?= htmlspecialchars($_GET['error_up']) ?></p>
                        <?php endif; ?>

                        <br>
                        <input class="confirm" type="submit" value="Sign up">
                    </div>
                </form>
            </div>

        <?php else: ?>
            <div class="right">
                <form id="formPayment" action="../src/Service/ConfirmOrder.php" method="post">
                    <div class="delivery">
                        <h2>Delivery Information</h2>
                        <div class="bottom">
                            <input type="text" id="address" placeholder="Address" name="address"
                                value="<?= $_SESSION['user_obj']->address ?? '' ?>" required>
                            <input type="text" id="postalCode" placeholder="Postal Code" name="postalCode" maxlength="5" minlength="5" required>
                            <input type="text" id="city" placeholder="City" name="city" required>
                            <select id="country" name="country" required>
                                <option value="" selected hidden>Country</option>
                                <option value="france">France</option>
                                <option value="espagne">Espagne</option>
                                <option value="portugal">Portugal</option>
                                <option value="allemagne">Allemagne</option>
                                <option value="japon">Japon</option>
                                <option value="italie">Italie</option>
                            </select>
                            <input type="tel" id="phone" name="phone" placeholder="Phone (1234567890)" pattern="[0-9]{10}" value="<?= $_SESSION['user_obj']->phone ?? '' ?>" minlength="10" maxlength="10" required>

                        </div>
                    </div>

                    <div class="payment">
                        <h2>Payment Information</h2>
                        <div class="bottom">
                            <label for="cardNumber">Card Number:</label>
                            <input type="text" id="cardNumber" name="cardNumber" value="4242 4242 4242 4242" required>
                            <label for="expiration">Expiration Date:</label>
                            <input type="text" id="expiration" name="expiration" value="12/34" required>
                            <label for="cvc">CVC:</label>
                            <input type="text" id="cvc" name="cvc" value="123" maxlength="3" required>

                        </div>

                        <p class="info">
                            Payment method simulated for project purposes (no real payment will be processed).
                        </p>

                        <?php if (isset($_GET['error_pay'])): ?>
                            <p class="error"><?= htmlspecialchars($_GET['error_pay']) ?></p>
                        <?php endif; ?>
                        <input type="submit" id="confirmOrder" value="Confirm my order">
                    </div>
                </form>
            </div>

        <?php endif; ?>

    </div>

    <footer>
        <a href="https://github.com/Draken1003/img2brick" target="_blank">GitHub</a>
    </footer>

    <script src="https://unpkg.com/friendly-challenge@0.9.3/widget.module.min.js" type="module"></script>
    <script src="./assets/js/sign.js"></script>
    <script>
        document.getElementById("cardNumber").addEventListener("keypress", function(event) {
            var key = event.keyCode;
            // Only allow numbers to be entered
            if (key < 48 || key > 57) {
                event.preventDefault();
            }
        });
        document.getElementById("postalCode").addEventListener("keypress", function(event) {
            var key = event.keyCode;
            // Only allow numbers to be entered
            if (key < 48 || key > 57) {
                event.preventDefault();
            }
        });
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