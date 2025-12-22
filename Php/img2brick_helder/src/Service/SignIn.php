<?php
require_once "../../connexion.inc.php";
require_once "../Model/Order.php";
require_once "../Model/User.php";
require_once '../../config/phpmailer_config.php';

session_start();

if (isset($_POST['email']) && isset($_POST['password'])) {

    // Verify Friendly Captcha
    $captcha_solution = $_POST['frc-captcha-solution'] ?? '';

    if (!$captcha_solution) {
        die("Captcha missing!");
    }

    $verify = curl_init("https://api.friendlycaptcha.com/api/v1/siteverify");
    curl_setopt($verify, CURLOPT_POST, true);
    curl_setopt($verify, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($verify, CURLOPT_POSTFIELDS, http_build_query([
        'solution' => $captcha_solution,
        'secret' => getenv('CAPTCHA_SECRETKEY')
    ]));

    $response = curl_exec($verify);
    curl_close($verify);

    $result = json_decode($response, true);

    if (!($result['success'] ?? false)) {
        header("Location: ../../public/order.php?error_in=" . urlencode("Invalid captcha !"));
    }


    $email = $_POST['email'];
    $password = $_POST['password'];

    $sql = "SELECT id, first_name, last_name, email, address, phone, is_verified, password_hash FROM users WHERE email = :email";
    $stmt = $cnx->prepare($sql);
    $stmt->bindParam(':email', $email, PDO::PARAM_STR);
    $stmt->execute();

    $user = $stmt->fetch(PDO::FETCH_ASSOC);


    if ($user && password_verify($password, $user['password_hash'])) {

        try {
            $cnx->beginTransaction();

            // Generate 2FA code (6 number)
            $otp = random_int(100000, 999999);
            $expires_at = date('Y-m-d H:i:s', time() + 60); // expires in 1 minutes

            // Update OTP code
            $update = $cnx->prepare("UPDATE users SET otp_code = :otp, otp_expires_at = :exp WHERE id = :id");
            $update->execute([
                ':otp' => $otp,
                ':exp' => $expires_at,
                ':id' => $user['id']
            ]);

            // Send mail
            $subject = "Your login code (2FA)";
            $body = "<p>Here is your code:</p><h2>$otp</h2><p>It expires in 1 minutes.</p>";

            if (!sendMail($email, $subject, $body, $user)) {
                $cnx->rollBack();
                exit("Error sending email.");
            }

            $cnx->commit();

            $user_obj = new User(
                $user['id'],
                $user['email'],
                $user['address'],
                $user['phone'],
                $user['first_name'],
                $user['last_name'],
                $user['is_verified'],
            );

            $_SESSION['user_obj'] = $user_obj;

            // Store the ID for verification
            $_SESSION['pending_2fa_user'] = $user['id'];

            header("Location: ../../public/verify_2fa.php");
            exit;
        } catch (Exception $e) {
            $cnx->rollBack();
            if (isset($_SESSION['order_obj']) && $_SESSION['order_obj']) {
                header("Location: ../../public/order.php?error_in=" . urlencode("Incorrect email or password"));
                exit;
            } else {
                header("Location: ../../public/sign_in.php?error_in=" . urlencode("Incorrect email or password"));
                exit;
            }
        }
    } else {
        if (isset($_SESSION['order_obj']) && $_SESSION['order_obj']) {
            header("Location: ../../public/order.php?error_in=" . urlencode("Incorrect email or password"));
            exit;
        } else {
            header("Location: ../../public/sign_in.php?error_in=" . urlencode("Incorrect email or password"));
            exit;
        }
    }
}
