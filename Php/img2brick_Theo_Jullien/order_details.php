<?php
session_start();
require_once "connection.inc.php";

// Redirect if user not login
if (!isset($_SESSION["user_id"])) {
    header("Location: connection.php");
    exit;
}

$cnx = getConnection();
$userId = $_SESSION["user_id"];
$orderId = $_GET['id'] ?? null;

if (!$orderId) {
    header("Location: orders_history.php");
    exit;
}

//  Retrieving order details with owner verification
try {
    $sql = "SELECT o.*, p.fileextension, p.image_data, p.filename 
            FROM user_order o 
            JOIN picture p ON o.picture_id = p.id 
            WHERE o.id = :order_id AND o.user_id = :user_id";
    $stmt = $cnx->prepare($sql);
    $stmt->execute([':order_id' => $orderId, ':user_id' => $userId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        header("Location: orders_history.php?error=notfound");
        exit;
    }

    $base64 = base64_encode($order['image_data']);
    $mime = ($order['fileextension'] === 'png') ? 'image/png' : 'image/jpeg';

    $colorMap = ['blue' => 12, 'red' => 8, 'bw' => 2];
    $numColors = $colorMap[$order['filter']] ?? 'N/A';

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details - #<?= htmlspecialchars($order['order_number']) ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="p-order-details">
<?php include "header.inc.php"; ?>

<main class="container">
    <header class="page-title">
        <div class="breadcrumb">
            <a href="orders_history.php"><i class="fa-solid fa-arrow-left"></i> Back to My Orders</a>
        </div>
        <h1>Order Details</h1>
        <p class="order-id-display">Reference: <strong><?= htmlspecialchars($order['order_number']) ?></strong></p>
    </header>

    <div class="details-grid">
        <section class="user-card mosaic-preview-card">
            <h3>Mosaic Details</h3>
            <div class="detail-image-wrapper">
                <img src="data:<?= $mime ?>;base64,<?= $base64 ?>"
                     class="filter-<?= $order['filter'] ?>"
                     alt="Your Mosaic">
            </div>
            <div class="technical-info">
                <div class="info-row">
                    <span>Palette:</span>
                    <strong><?= ucfirst($order['filter']) ?> version</strong>
                </div>
                <div class="info-row">
                    <span>Resolution:</span>
                    <strong><?= $order['size'] ?> x <?= $order['size'] ?></strong>
                </div>
                <div class="info-row">
                    <span>Total Bricks:</span>
                    <strong><?= $order['size'] * $order['size'] ?> bricks</strong>
                </div>
                <div class="info-row">
                    <span>Unique Colors:</span>
                    <strong><?= $numColors ?> colors</strong>
                </div>
            </div>
        </section>

        <div class="info-column">
            <section class="user-card status-card">
                <h3>General Information</h3>
                <div class="info-row">
                    <span>Date:</span>
                    <strong><?= date('F d, Y - H:i', strtotime($order['created_at'])) ?></strong>
                </div>
                <div class="info-row">
                    <span>Status:</span>
                    <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $order['status'] ?? 'processing')) ?>">
                            <?= htmlspecialchars($order['status'] ?? 'In Preparation') ?>
                        </span>
                </div>
                <div class="info-row price-row">
                    <span>Amount Paid:</span>
                    <strong class="total-price"><?= number_format($order['amount'], 2) ?> €</strong>
                </div>
            </section>

            <section class="user-card shipping-card">
                <h3>Shipping Address <i class="fa-solid fa-truck-fast"></i></h3>
                <div class="address-box">

                    <p>
                        <?= nl2br(htmlspecialchars($order['shipping_address'])) ?><br>
                        <strong>Phone:</strong> <?= htmlspecialchars($order['phone'] ?? 'Not provided') ?>
                    </p>
                </div>
            </section>

            <!--
            <div class="action-box">
                <button class="btn-secondary" onclick="window.print()">
                    <i class="fa-solid fa-print"></i> Print Invoice
                </button>
                <a href="index.php" class="btn-login">Order Another One</a>
            </div>
            !-->
        </div>
    </div>
</main>

<?php include "footer.inc.php"; ?>
</body>
</html>