<?php
require_once "../../connexion.inc.php";
require_once "../Model/User.php";
require_once '../../config/phpmailer_config.php';

session_start();

if (isset($_POST['email']) && isset($_POST['password']) && isset($_POST['lastName']) && isset($_POST['firstName']) && isset($_POST['address'])) {

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

    //Get data from the form
    $email = $_POST['email'];
    $password = $_POST['password'];
    $lastName = $_POST['lastName'];
    $firstName = $_POST['firstName'];
    $address = $_POST['address'];

    // Password validation
    $passwordPattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/';
    if (!preg_match($passwordPattern, $password)) {
        $error_msg = 'Password must include at least one lowercase letter, one uppercase letter, one number, and one special character.';
        if (isset($_SESSION['order_obj']) && $_SESSION['order_obj']) {
            header("Location: ../../public/order.php?error_in=" . urlencode($error_msg));
        } else {
            header("Location: ../../public/sign_in.php?error_in=" . urlencode($error_msg));
        }
        exit;
    }

    // Verify if email already exists
    $sql = "SELECT id FROM users WHERE email = :email";
    $stmt = $cnx->prepare($sql);
    $stmt->bindParam(':email', $email, PDO::PARAM_STR);
    $stmt->execute();
    if ($stmt->fetch(PDO::FETCH_ASSOC)) {
        $error_msg = 'Unable to create the account. Please check your information and try again.';
        if (isset($_SESSION['order_obj']) && $_SESSION['order_obj']) {
            header("Location: ../../public/order.php?error_in=" . urlencode($error_msg));
        } else {
            header("Location: ../../public/sign_in.php?error_in=" . urlencode($error_msg));
        }
        exit;
    } else {

        $token = bin2hex(random_bytes(32));

        $cnx->beginTransaction();
        $sql = "INSERT INTO users ( first_name, last_name, email, password_hash, created_at, is_verified,  address, verification_token) 
                    VALUES (:first_name, :last_name, :email, :password_hash, :created_at, :is_verified, :address, :verification_token)";
        $stmt = $cnx->prepare($sql);

        $created_at = date('Y-m-d H:i:s');
        $verified = false;
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->bindParam(':password_hash', $password_hash, PDO::PARAM_STR);
        $stmt->bindParam(':last_name', $lastName, PDO::PARAM_STR);
        $stmt->bindParam(':first_name', $firstName, PDO::PARAM_STR);
        $stmt->bindParam(':created_at', $created_at, PDO::PARAM_STR);
        $stmt->bindParam(':is_verified', $verified, PDO::PARAM_BOOL);
        $stmt->bindParam(':address', $address, PDO::PARAM_STR);
        $stmt->bindParam(':verification_token', $token, PDO::PARAM_STR);

        if ($stmt->execute()) {
            $user = new User(
                $cnx->lastInsertId(), //user id
                $email,
                $address,
                null,
                $firstName,
                $lastName,
                $verified
            );

            $_SESSION['user_obj'] = $user;

            //Send the registration confirmation email.
            $subject = "Confirm your registration";
            $body = "<p>Click the button below to confirm your registration:</p>
                    <a href='http://localhost:8000/src/Service/VerifyMail.php?token=" . urlencode($token) . "' style='padding: 10px 20px; background-color: #4CAF50; color: white; text-decoration: none; border-radius: 5px;'>Confirm Registration</a>";

            if (sendMail($email, $subject, $body, $user)) {
                $cnx->commit();
                $success_msg = 'Please check your email to confirm your registration.';
                if (isset($_SESSION['order_obj']) && $_SESSION['order_obj']) {
                    header("Location: ../../public/order.php?success_up=" . urlencode($success_msg));
                } else {
                    header("Location: ../../public/sign_in.php?success_up=" . urlencode($success_msg));
                }
                exit;
            } else {
                $error_msg = 'Mail can\'t be sent';
                if (isset($_SESSION['order_obj']) && $_SESSION['order_obj']) {
                    header("Location: ../../public/order.php?error_in=" . urlencode($error_msg));
                } else {
                    header("Location: ../../public/sign_in.php?error_in=" . urlencode($error_msg));
                }
                exit;
            }
        } else {
            $cnx->rollBack();
            $error_msg = 'Error during registration.';
            if (isset($_SESSION['order_obj']) && $_SESSION['order_obj']) {
                header("Location: ../../public/order.php?error_in=" . urlencode($error_msg));
            } else {
                header("Location: ../../public/sign_in.php?error_in=" . urlencode($error_msg));
            }
        }
    }
}
