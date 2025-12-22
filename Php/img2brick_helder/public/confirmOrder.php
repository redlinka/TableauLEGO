<?php
require_once '../connexion.inc.php';
require_once "../src/Model/Order.php";
require_once "../src/Model/Image.php";

session_start();


if (isset($_SESSION['order_obj']) && isset($_SESSION['image_obj'])) {

    $order = $_SESSION['order_obj'];
    $image = $_SESSION['image_obj'];
}

if (!isset($_SESSION['image_id'])) {
    die("Aucune image select");
}

$id = intval($_SESSION['image_id']);
$sql = "SELECT filename, mime_type, image_blob FROM images WHERE id = :id";
$stmt = $cnx->prepare($sql);
$stmt->bindParam(':id', $id, PDO::PARAM_INT);
$stmt->execute();
$image = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$image) {
    die("Image not found.");
}

$typeMime = htmlspecialchars($image['mime_type']);
$img_blob = base64_encode($image['image_blob']);
$palette_choice = $order->palette_choice;

$mosaicVariants = [
    'blue' => 'filter: hue-rotate(180deg) saturate(200%);',
    'red' => 'filter: hue-rotate(-50deg) saturate(200%);',
    'bw' => 'filter: grayscale(1);',
];

$style = $mosaicVariants[$palette_choice];
$delivery_address = $_SESSION['order_obj']->addresse . ", " . $_SESSION['order_obj']->city . ", " . $_SESSION['order_obj']->postal_code . ", " . $_SESSION['order_obj']->country;

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/confirmOrder.css">
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
            } else {
                echo '<a href="sign-in.php" class="sign-in">Sign in</a>';
            }
            ?>
        </div>
    </header>
    <div class="container">
        <div class="confirmation-container">
            <div class="left">
                <h1>Thank you for your order!</h1>
                <p>Your mosaic is being prepared. You will receive a confirmation email at the address you provided.</p>
                <br>
                <!-- order recap -->
                <div class="order-summary">
                    <div>
                        <strong>Order number :</strong>
                        <span><?php echo $order->id_order; ?></span>
                    </div>
                    <div>
                        <strong>Delivery address :</strong>
                        <span><?php echo $delivery_address; ?></span>
                    </div>
                    <div>
                        <strong>Amount paid :</strong>
                        <span><?php echo number_format($order->price_cents / 100, 2, ',', ' ') ?> €</span>
                    </div>
                </div>
                <br>
                <a href="index.php" class="btn">New order</a>
            </div>
            <img src="data:<?= $typeMime ?>;base64,<?= $img_blob ?>" style="<?= $style ?>" alt="Selected image">
        </div>
    </div>
    <footer>
        <a href="https://github.com/Draken1003/img2brick" target="_blank">GitHub</a>
    </footer>
</body>

</html>