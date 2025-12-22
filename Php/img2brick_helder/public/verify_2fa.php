<?php
require_once "../connexion.inc.php";
require_once "../src/Model/Order.php";
require_once "../src/Model/Image.php";
session_start();

if (!isset($_SESSION['pending_2fa_user'])) {
    header("Location: order.php?error_in=" . urlencode("Session expired. Please log in again."));
}

if (isset($_POST['otp'])) {
    $otp = $_POST['otp'];
    $user_id = $_SESSION['pending_2fa_user'];

    $cnx->beginTransaction();

    try {
        // Verify the OTP code in the database
        $stmt = $cnx->prepare("SELECT otp_code, otp_expires_at FROM users WHERE id = :id");
        $stmt->execute([':id' => $user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && $user['otp_code'] == $otp && strtotime($user['otp_expires_at']) > time()) {
            // Valid code -> final connexion

            // Regenerate the session ID for added security
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user_id;


            // Delete the OTP and expiration date from the database
            $update = $cnx->prepare("UPDATE users SET otp_code = NULL, otp_expires_at = NULL WHERE id = :id");
            $update->bindParam(':id', $user_id, PDO::PARAM_INT);
            $update->execute();

            // Delete the temporary session variable
            unset($_SESSION['pending_2fa_user']);

            //In order page
            if (isset($_SESSION['order_obj']) && $_SESSION['order_obj']) {
                $_SESSION['order_obj']->user_id = $user_id;
                // Update the image user_id
                $updateImage = $cnx->prepare("UPDATE images SET user_id = :user_id WHERE id = :id");
                $imageId = $_SESSION['image_obj']->img_id;
                $updateImage->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $updateImage->bindParam(':id', $imageId, PDO::PARAM_INT);
                $updateImage->execute();

                $cnx->commit();

                header("Location: order.php");
                exit;
            } else {
                //In sign page
                $cnx->commit();
                header("Location: index.php");
                exit;
            }
        } else {
            header("Location: order.php?error_in=" . urlencode("Invalid or expired code."));
        }
    } catch (Exception $e) {
        $cnx->rollBack();
        if (isset($_SESSION['order_obj']) && $_SESSION['order_obj']) {
            header("Location: order.php?error_in=" . urlencode("Invalid or expired code."));
        } else {
            header("Location: sign_in.php?error_in=" . urlencode("Invalid or expired code."));
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            display: flex;
            justify-content: center;
            align-items: center;
        }

        form {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            box-shadow: 0 0 20px 0.1px var(--fourth-color);
            background-color: var(--secondary-color);
            padding: 20px;
            border-radius: 10px;
            max-width: fit-content;
        }

        form>input {
            width: 100%;
        }
    </style>
</head>

<body>
    <div class="bg-circle"></div>
    <form method="post">
        <h2>Enter the code you received by email:</h2><br>
        <input type="text" placeholder="Code" name="otp" maxlength="6" required><br>
        <input type="submit" value="Validate"></input>
    </form>
</body>

</html>