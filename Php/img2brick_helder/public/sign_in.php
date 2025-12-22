<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/sign.css">

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
                echo '<a href="account.php" class="profil"><img width="30" height="30" src="https://img.icons8.com/?size=100&id=85147&format=png&color=ffffff" /></a>';
            }
            ?>
        </div>
    </header>
    <div class="container">
        <?php
        echo '
                <!-- Form sign in -->
                <form id="signInForm" class="sign active" action="../src/Service/SignIn.php" method="post">
                    <h1>Sign in</h1><br>
                    <div class="sign-form">
                    <input type="email" id="email" placeholder="Email" name="email" required>
                    <input type="password" id="password" placeholder="Password" name="password"  required>
                    <p>No account ? <a class="toggle" id="toSignUp">Sign up</a></p>
                    <p>Forgotten password ? <a class="toggle" href="./recover_form.php">Recover pass.</a></p>
                    <div class="frc-captcha" style="border-radius:10px; margin-top:10px" data-sitekey="' . getenv('CAPTCHA_SITEKEY') . '"></div>
                    </div>
                    ';
        if (isset($_GET['error_in'])) {
            $error = htmlspecialchars($_GET['error_in']);
            echo '<p class="error">' . $error . '</p>';
        }
        if (isset($_GET['success_up'])) {
            $error = htmlspecialchars($_GET['success_up']);
            echo '<p class="valid">' . $error . '</p>';
        }

        echo   '<br><input type="submit" value="Sign in">
                </form>

                <!-- Form sign up -->
                <form id="signUpForm" action="../src/Service/SignUp.php" class="sign" method="post">
                    <h1>Sign up</h1><br>
                    <div class="sign-form">
                    <input type="text" id="lastName" placeholder="Last name" name="lastName" required>
                    <input type="text" id="firstName" placeholder="First name" name="firstName" required>
                    <input type="text" id="address" placeholder="Address" name="address" required>
                    <input type="email" id="email" placeholder="Email" name="email" required>
                    <input type="password" id="password" placeholder="Password" name="password" required minlength="12">
                    <p>Already have an account ? <a class="toggle" id="toSignIn">Sign in</a></p>
                    <div class="frc-captcha" style="border-radius:10px; margin-top:10px" data-sitekey="' . getenv('CAPTCHA_SITEKEY') . '"></div>
                    </div>
                    ';
        if (isset($_GET['error_up'])) {
            $error = htmlspecialchars($_GET['error_up']);
            echo '<p class="error">' . $error . '</p>';
        }

        echo    '<br><input type="submit" value="Sign up">
                </form>
            ';
        ?>
    </div>
    <footer>
        <a href="https://github.com/Draken1003/img2brick" target="_blank">GitHub</a>
    </footer>

    <script src="https://unpkg.com/friendly-challenge@0.9.3/widget.module.min.js" type="module"></script>
    <script src="assets/js/sign.js"></script>
</body>

</html>