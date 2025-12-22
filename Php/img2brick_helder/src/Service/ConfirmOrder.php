<?php
require_once "../../connexion.inc.php";
require_once '../Model/Order.php';
require_once "../Model/User.php";
require_once '../../config/phpmailer_config.php';

session_start();


if (isset($_SESSION['order_obj'])) {

    $created_at = date('Y-m-d H:i:s');
    $_SESSION['order_obj']->status = "preparing";
    $_SESSION['order_obj']->addresse = $_POST['address'];
    $_SESSION['order_obj']->postal_code = $_POST['postalCode'];
    $_SESSION['order_obj']->city = $_POST['city'];
    $_SESSION['order_obj']->country = $_POST['country'];
    $_SESSION['order_obj']->phone = $_POST['phone'];
    $_SESSION['order_obj']->created_at = $created_at;

    try {

        $cnx->beginTransaction();

        $stmt = $cnx->prepare("INSERT INTO orders (user_id, image_id, size, palette_choice, price_cents, status, address, postal_code, city, country, phone, created_at) 
                VALUES (:user_id, :image_id, :size, :palette_choice, :price_cents, :status, :addresse, :postal_code, :city, :country, :phone, :created_at)
            ");

        $stmt->bindParam(':user_id', $_SESSION['order_obj']->user_id, PDO::PARAM_INT);
        $stmt->bindParam(':image_id', $_SESSION['order_obj']->image_id, PDO::PARAM_INT);
        $stmt->bindParam(':size', $_SESSION['order_obj']->size, PDO::PARAM_STR);
        $stmt->bindParam(':palette_choice', $_SESSION['order_obj']->palette_choice, PDO::PARAM_STR);
        $stmt->bindParam(':price_cents', $_SESSION['order_obj']->price_cents, PDO::PARAM_INT);
        $stmt->bindParam(':status', $_SESSION['order_obj']->status, PDO::PARAM_STR);
        $stmt->bindParam(':addresse', $_SESSION['order_obj']->addresse, PDO::PARAM_STR);
        $stmt->bindParam(':postal_code', $_SESSION['order_obj']->postal_code, PDO::PARAM_STR);
        $stmt->bindParam(':city', $_SESSION['order_obj']->city, PDO::PARAM_STR);
        $stmt->bindParam(':country', $_SESSION['order_obj']->country, PDO::PARAM_STR);
        $stmt->bindParam(':phone', $_SESSION['order_obj']->phone, PDO::PARAM_STR);
        $stmt->bindParam(':created_at', $_SESSION['order_obj']->created_at, PDO::PARAM_STR);

        if (!$stmt->execute()) {
            throw new Exception("Error inserting order into database.");
        }
        $_SESSION['order_obj']->id_order = $cnx->lastInsertId();

        // Send mail
        $subject = "Your Order Confirmation - Img2Brick";
        $order_number = $_SESSION['order_obj']->id_order;
        $delivery_address = $_SESSION['order_obj']->addresse . ", " . $_SESSION['order_obj']->city . ", " . $_SESSION['order_obj']->postal_code . ", " . $_SESSION['order_obj']->country;
        $formatted_amount = number_format($_SESSION['order_obj']->price_cents / 100, 2, '.', ',');
        $body = "
                <html>
                    <body style='font-family: Arial, sans-serif; background-color: #f9f9f9; color: #333;'>
                        <div style='background-color: #ffffff; padding: 20px; margin: 0 auto; width: 80%; max-width: 600px; border-radius: 8px;'>
                            <h2 style='color: #4CAF50;'>Thank you for your order!</h2>
                            <p>Your mosaic is being prepared. You will receive a confirmation email at the address you provided.</p>
                            
                            <h3>Order Summary:</h3>
                            <p><strong>Order Number:</strong> $order_number</p>
                            <p><strong>Delivery Address:</strong> $delivery_address</p>
                            <p><strong>Amount Paid:</strong> $formatted_amount &#128;</p>
                            
                            <p>We will send you an email once your order has been shipped.</p> 
                        </div>
                    </body>
                </html>
                ";

        if (!sendMail($_SESSION['user_obj']->email, $subject, $body, $_SESSION['user_obj'])) {
            throw new Exception("Failed to send confirmation email.");
        }

        $cnx->commit();

        header("Location: ../../public/confirmOrder.php");
        exit;
    } catch (Exception $e) {

        if ($cnx->inTransaction()) {
            $cnx->rollBack();
        }
        header("Location: ../../public/order.php?error_pay=" . urlencode($e->getMessage()));
        exit;
    }
} else {
    header("Location: ../../public/order.php?error_pay=" . urlencode("No order found to process."));
}
