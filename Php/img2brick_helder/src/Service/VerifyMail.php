<?php
require_once "../../connexion.inc.php";
require_once "../Model/Order.php";
require_once "../Model/Image.php";

session_start();

if (isset($_GET['token'])) {
    $token = $_GET['token'];

    // Check if the token exists in the database
    $sql = "SELECT id, verification_token FROM users WHERE verification_token = :token";
    $stmt = $cnx->prepare($sql);
    $stmt->bindParam(':token', $token, PDO::PARAM_STR);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $cnx->beginTransaction();
        try {
            // User activation
            $update = $cnx->prepare("UPDATE users SET is_verified = 1, verification_token = NULL WHERE id = :id");
            $update->bindParam(':id', $user['id'], PDO::PARAM_INT);
            $update->execute();

            $_SESSION['user_id'] = $user['id'];
            if (isset($_SESSION['order_obj'])) {
                $_SESSION['order_obj']->user_id = $user['id'];
            }

            // Update the image if it exists
            if (isset($_SESSION['order_obj']) && $_SESSION['order_obj'] && isset($_SESSION['image_obj']) && $_SESSION['image_obj']->img_id) {
                $updateImage = $cnx->prepare("UPDATE images SET user_id = :user_id WHERE id = :id");
                $imageId = $_SESSION['image_obj']->img_id;
                $updateImage->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
                $updateImage->bindParam(':id', $imageId, PDO::PARAM_INT);
                $updateImage->execute();

                $cnx->commit();
                header("Location: ../../public/order.php");
                exit;
            } else {
                $cnx->commit();
                header("Location: ../../public/index.php");
                exit;
            }
        } catch (Exception $e) {
            $cnx->rollBack();
            if (isset($_SESSION['order_obj']) && $_SESSION['order_obj']) {
                header("Location: ../../public/order.php?error_up=" . urlencode("Activation failed."));
            } else {
                header("Location: ../../public/sign_in.php?error_up=" . urlencode("Activation failed."));
            }

            exit;
        }
    } else {
        if (isset($_SESSION['order_obj']) && $_SESSION['order_obj']) {
            header("Location: ../../public/order.php?error_up=" . urlencode('Invalid token or account already verified.'));
        } else {
            header("Location: ../../public/sign_in.php?error_up=" . urlencode("Invalid token or account already verified."));
        }
        exit;
    }
}
