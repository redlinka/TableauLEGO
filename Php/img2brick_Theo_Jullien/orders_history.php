<?php
session_start();
require_once "connection.inc.php";

// Leave if user is not login
if (!isset($_SESSION["user_id"])) {
    header("Location: connection.php");
    exit;
}

$cnx = getConnection();
$userId = $_SESSION["user_id"];

// 1. Retrieving the user's order list
try {
    $sql = "SELECT o.*, p.fileextension, p.image_data 
            FROM user_order o 
            JOIN picture p ON o.picture_id = p.id 
            WHERE o.user_id = :user_id 
            ORDER BY o.created_at DESC";
    $stmt = $cnx->prepare($sql);
    $stmt->execute([':user_id' => $userId]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching orders: " . $e->getMessage();
}
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - Img2Brick</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="p-orders-list">
<?php include "header.inc.php"; ?>

<main class="container">
    <header class="page-title">
        <h1>My Orders</h1>
        <p>Here you can find all your previous orders. Click on an order to see the details.</p>
        <a href="index.php" class="btn-secondary" style="display:inline-block; margin-top:10px;">
            <i class="fa-solid fa-plus"></i> New Order
        </a>
    </header>

    <?php if (isset($error)): ?>
        <div class="alert alert-error"><?= $error ?></div>
    <?php endif; ?>

    <section class="orders-section">
        <?php if (empty($orders)): ?>
            <div class="user-card" style="text-align:center; padding: 50px;">
                <p>You haven't placed any orders yet.</p>
                <a href="index.php" class="btn-login">Create my first mosaic</a>
            </div>
        <?php else: ?>
            <div class="orders-grid">
                <?php foreach ($orders as $order):
                    $base64 = base64_encode($order['image_data']);
                    $mime = ($order['fileextension'] === 'png') ? 'image/png' : 'image/jpeg';

                    $statusClass = 'status-' . strtolower(str_replace(' ', '-', $order['status'] ?? 'processing'));
                    ?>
                    <article class="user-card order-item">
                        <div class="order-main-info">
                            <div class="order-thumbnail">
                                <img src="data:<?= $mime ?>;base64,<?= $base64 ?>"
                                     class="filter-<?= $order['filter'] ?>"
                                     alt="Order Thumbnail">
                            </div>
                            <div class="order-summary-text">
                                <h3>Order #<?= htmlspecialchars($order['order_number']) ?></h3>
                                <p class="order-date">Placed on: <?= date('M d, Y', strtotime($order['created_at'])) ?></p>
                                <span class="status-badge <?= $statusClass ?>">
                                        <?= htmlspecialchars($order['status'] ?? 'In Preparation') ?>
                                    </span>
                            </div>
                        </div>

                        <div class="order-meta">
                            <div class="meta-item">
                                <span>Size:</span>
                                <strong><?= $order['size'] ?>x<?= $order['size'] ?></strong>
                            </div>
                            <div class="meta-item">
                                <span>Palette:</span>
                                <strong><?= ucfirst($order['filter']) ?></strong>
                            </div>
                            <div class="meta-item">
                                <span>Amount:</span>
                                <strong><?= number_format($order['amount'], 2) ?> €</strong>
                            </div>
                        </div>

                        <div class="order-actions">
                            <a href="order_details.php?id=<?= $order['id'] ?>" class="btn-details">
                                View Details <i class="fa-solid fa-chevron-right"></i>
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>

<?php include "footer.inc.php"; ?>
</body>
</html>