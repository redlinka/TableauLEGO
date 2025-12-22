<?php
require_once "session.php";

if (!isset($_SESSION["user"])) {
    header("Location: log_out.php");
    exit;
}

$user = $_SESSION["user"];
$id_uti = $user->get_id();


if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"], $_POST["id_img"])) {

    $id_img = (int)$_POST["id_img"];

    if ($_POST["action"] === "increase") {

        $stmt = $pdo->prepare("
            UPDATE panier
            SET nb = nb + 1
            WHERE code_uti = :id_uti AND id_img = :id_img
        ");
        $stmt->execute([
            ':id_uti' => $id_uti,
            ':id_img' => $id_img
        ]);

    } elseif ($_POST["action"] === "decrease") {

        $stmt = $pdo->prepare("
            UPDATE panier
            SET nb = nb - 1
            WHERE code_uti = :id_uti AND id_img = :id_img
        ");
        $stmt->execute([
            ':id_uti' => $id_uti,
            ':id_img' => $id_img
        ]);


        $stmt = $pdo->prepare("
            DELETE FROM panier
            WHERE code_uti = :id_uti AND id_img = :id_img AND nb <= 0
        ");
        $stmt->execute([
            ':id_uti' => $id_uti,
            ':id_img' => $id_img
        ]);
    }

    header("Location: cart.php");
    exit;
}


$stmt = $pdo->prepare("SELECT * FROM panier WHERE code_uti = :id");
$stmt->execute([":id" => $id_uti]);
$row_pan = $stmt->fetchAll(PDO::FETCH_ASSOC);


$items = [];
$subtotal = 0.0;

foreach ($row_pan as $row) {

    $id_img = (int)$row["id_img"];
    $qty    = (int)$row["nb"];

    $imgObj = get_image($pdo, $id_img);
    if (!$imgObj) continue;

    $src   = $imgObj->get_img();
    $price = (float)$imgObj->get_price(); 

    $lineTotal = $price * $qty;
    $subtotal += $lineTotal;

    $items[] = [
        "id_img" => $id_img,
        "src" => $src,
        "qty" => $qty,
        "price" => $price,
        "line_total" => $lineTotal
    ];
}

$shipping = $subtotal * 0.10;
$total = $subtotal + $shipping;


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Cart</title>
    <link rel="stylesheet" href="css/cart.css">
</head>
<body>

<main class="cart-page">
</br>
    <h1 class="title">My Cart</h1>

    <div class="cart-layout">

 
        <section class="cart-left">
            <h2 class="section-title">Items</h2>

            <?php if (empty($items)): ?>
                <p class="empty">Your cart is empty.</p>
            <?php else: ?>
                <div class="items-grid">
                    <?php foreach ($items as $it): ?>
                        <article class="item-card">

                            <div class="thumb">
                                <img src="<?= $it["src"] ?>" alt="Item">
                            </div>

                            <div class="meta">

                                <div class="qty-row">
                                    <form method="post" class="qty-form">
                                        <input type="hidden" name="action" value="decrease">
                                        <input type="hidden" name="id_img" value="<?= $it["id_img"] ?>">
                                        <button type="submit" class="qty-btn">−</button>
                                    </form>

                                    <span class="qty-value"><?= $it["qty"] ?></span>

                                    <form method="post" class="qty-form">
                                        <input type="hidden" name="action" value="increase">
                                        <input type="hidden" name="id_img" value="<?= $it["id_img"] ?>">
                                        <button type="submit" class="qty-btn">+</button>
                                    </form>
                                </div>

                                <div class="meta-row">
                                    <span>Unit</span>
                                    <strong><?= money($it["price"]) ?></strong>
                                </div>

                                <div class="meta-row">
                                    <span>Line</span>
                                    <strong><?= money($it["line_total"]) ?></strong>
                                </div>

                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>


        <aside class="cart-right">
            <h2 class="section-title">Summary</h2>

            <div class="summary-box">
                <div class="sum-row">
                    <span>Subtotal</span>
                    <strong><?= money($subtotal) ?></strong>
                </div>

                <div class="sum-row">
                    <span>Shipping (10%)</span>
                    <strong><?= money($shipping) ?></strong>
                </div>

                <div class="sum-divider"></div>

                <div class="sum-row total">
                    <span>Total</span>
                    <strong><?= money($total) ?></strong>
                </div>

                <form method="post" action="order.php">
                    <button type="submit" class="order-btn" <?= empty($items) ? "disabled" : "" ?>>
                        Order
                    </button>
                </form>

                <a href="index.php" class="back-link">Back to start</a>
            </div>
        </aside>

    </div>
</main>

</body>
</html>
