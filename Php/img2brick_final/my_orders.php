<?php
session_start();
global $cnx;
include("./config/cnx.php");
require_once __DIR__ . '/includes/i18n.php';

$_SESSION['redirect_after_login'] = 'my_orders.php';
if (!isset($_SESSION['userId'])) {
    header("Location: connexion.php");
    exit();
}

$userId = (int)$_SESSION['userId'];

function money($v)
{
    return number_format((float)$v, 2, ".", " ") . " EUR";
}

try {
    // Retrieves validated ORDER only (where created_at is not null because if it's null, it's a pending order (cart)
    $stmt = $cnx->prepare("SELECT order_id, created_at, address_id FROM ORDER_BILL WHERE user_id = ? AND created_at IS NOT NULL ORDER BY created_at DESC");
    $stmt->execute([$userId]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erreur : " . $e->getMessage());
    // create the safe error for users
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>My orders - Img2Brick</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .order-card {
            border: 1px solid #ddd;
            margin-bottom: 20px;
            border-radius: 8px;
            overflow: hidden;
        }

        .order-header {
            background: #f8f9fa;
            padding: 15px;
            display: flex;
            justify-content: space-between;
            border-bottom: 1px solid #ddd;
        }

        .order-body {
            padding: 15px;
        }

        .item-row {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }

        .item-row img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 4px;
        }

        .status-badge {
            background: #28a745;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.8em;
        }
    </style>
</head>

<body>
    <?php include("./includes/navbar.php"); ?>

    <main class="container">
        <h1 data-i18n="my_orders.title">My orders</h1>

        <?php if (empty($orders)): ?>
            <p>You have not placed an order yet.</p>
        <?php else: ?>
            <?php foreach ($orders as $order): ?>
                <div class="order-card">
                    <div class="order-header">
                        <div>
                            <strong>Order #<?= $order['order_id'] ?></strong><br>
                            <small>Placed on : <?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></small>
                        </div>
                        <div>
                            <span class="status-badge">Paid</span>
                        </div>
                    </div>

                    <div class="order-body">
                        <?php
                        // Retrieve tiling for this order to display it
                        $stmtItems = $cnx->prepare("SELECT t.pavage_txt, i.path as lego_path, t.pavage_id, i.image_id FROM contain c 
                                                JOIN TILLING t ON c.pavage_id = t.pavage_id 
                                                JOIN IMAGE i ON t.image_id = i.image_id 
                                                WHERE c.order_id = ?");
                        $stmtItems->execute([$order['order_id']]);
                        $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

                        $orderTotal = 0;
                        foreach ($items as $item):
                            $filename = getOriginalImage($cnx, $item['image_id'])["filename"];
                            $stats = getTilingStats($item['pavage_txt']);
                            $price = $stats['price'] / 100;
                            $orderTotal += $price;
                        ?>
                            <div class="item-row">
                                <img src="users/imgs/<?= htmlspecialchars($item['lego_path']) ?>" alt="Overview">
                                <div style="flex: 1;">
                                    <strong>File : <?= htmlspecialchars($filename) ?></strong><br>
                                    <small>Quality : <?= $stats['quality'] ?>%</small>
                                    <br>
                                    <a href="generate_manual.php?file=<?= urlencode($item['pavage_txt']) ?>" target="_blank" class="btn btn-sm btn-outline-primary mt-2">
                                        View Guide
                                    </a>
                                    <a href="users/imgs/<?= htmlspecialchars($item['lego_path']) ?>" download class="btn btn-sm btn-outline-secondary mt-2">
                                        Download Image
                                    </a>
                                    <a href="users/tilings/<?= htmlspecialchars($item['pavage_txt']) ?>" download class="btn btn-sm btn-outline-secondary mt-2">
                                        Download Tiling
                                    </a>
                                </div>
                                <div><?= money($price) ?></div>
                            </div>
                        <?php endforeach; ?>

                        <div style="text-align: right; margin-top: 15px;">
                            <strong>Subtotal : <?= money($orderTotal) ?></strong><br>
                            <small>Shipping costs (10%): <?= money($orderTotal * 0.1) ?></small><br>
                            <strong>Total : <?= money($orderTotal * 1.1) ?></strong>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>
    <?php include("./includes/footer.php"); ?>
</body>

</html>