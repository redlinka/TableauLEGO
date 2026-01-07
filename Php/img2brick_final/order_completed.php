<?php
session_start();
global $cnx;
include("./config/cnx.php");
require_once __DIR__ . '/includes/i18n.php';

// Enforce authentication
if (!isset($_SESSION['userId'])) {
    header("Location: connexion.php");
    exit;
}

// Verify order session
if (!isset($_SESSION['last_order_id'])) {
    header("Location: index.php");
    exit;
}

$userId = $_SESSION['userId'];
$orderId = $_SESSION['last_order_id'];

try {
    // Retrieve order details
    $sql = "SELECT 
                    o.id_order, 
                    o.total_price, 
                    o.status AS order_status, 
                    o.shipping_address, 
                    o.first_name, o.last_name, o.phone,
                    o.created_at,
                    i.filename
                FROM Orders o
                JOIN Images i ON o.image_id = i.id_image
                WHERE o.id_order = ? AND o.user_id = ?";

    $stmt = $cnx->prepare($sql);
    $stmt->execute([$orderId, $userId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        // Handle invalid or unauthorized access
        die("Order not found or access denied.");
    }

} catch (PDOException $e) {
    die("System Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars(tr('order_completed.page_title', 'Order Confirmed - Img2Brick')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .conf-icon { font-size: 4rem; color: #198754; }
        .lego-preview { width: 100%; max-width: 300px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); image-rendering: pixelated; }
        .status-badge { font-size: 0.9rem; padding: 0.5em 1em; border-radius: 20px; }
    </style>
</head>
<body>

<?php include("./includes/navbar.php"); ?>

<div class="container bg-light py-5">

    <div class="text-center mb-5">
        <div class="conf-icon">OK</div>
        <h1 class="fw-bold mt-2" data-i18n="order_completed.title">Order Successfully Placed!</h1>
        <p class="text-muted" data-i18n="order_completed.subtitle">Thank you for your purchase!</p>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header bg-white p-4 border-bottom-0">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><span data-i18n="order_completed.order_label">Order</span> #<?= htmlspecialchars($order['id_order']) ?></h5>
                        <?php
                        $statusClass = ($order['order_status'] === 'PREPARATION') ? 'bg-warning text-dark' : 'bg-primary text-white';
                        $statusMap = [
                            'PREPARATION' => 'orders.status.preparation',
                            'SHIPPED' => 'orders.status.shipped',
                            'DELIVERED' => 'orders.status.delivered',
                            'CANCELLED' => 'orders.status.cancelled',
                        ];
                        $statusKey = $statusMap[$order['order_status']] ?? null;
                        $statusLabel = $statusKey ? tr($statusKey, $order['order_status']) : $order['order_status'];
                        ?>
                        <span class="badge <?= $statusClass ?> status-badge">
                                <?= htmlspecialchars($statusLabel) ?>
                            </span>
                    </div>
                </div>

                <div class="card-body p-4">
                    <div class="row g-4">

                        <div class="col-md-5 text-center">
                            <h6 class="text-muted mb-3" data-i18n="order_completed.custom_kit">Your Custom Kit</h6>
                            <img src="users/imgs/<?= htmlspecialchars($order['filename']) ?>" class="lego-preview" alt="Lego Mosaic">
                        </div>

                        <div class="col-md-7">
                            <h6 class="text-muted border-bottom pb-2" data-i18n="order_completed.delivery">Delivery Details</h6>

                            <p class="mb-1 fw-bold"><?= htmlspecialchars($order['first_name'] . ' ' . $order['last_name']) ?></p>
                            <p class="mb-3 text-muted small"><?= htmlspecialchars(tr('order_completed.phone_label', 'Phone:')) ?> <?= htmlspecialchars($order['phone']) ?></p>

                            <p class="mb-4">
                                <?= nl2br(htmlspecialchars($order['shipping_address'])) ?>
                            </p>

                            <h6 class="text-muted border-bottom pb-2" data-i18n="order_completed.payment">Payment Summary</h6>
                            <div class="d-flex justify-content-between mb-2">
                                <span data-i18n="order_completed.kit">Mosaic Kit</span>
                                <span>$<?= htmlspecialchars($order['total_price']) ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span data-i18n="order_completed.shipping">Shipping</span>
                                <span class="text-success" data-i18n="order_completed.free">Free</span>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between fs-5 fw-bold">
                                <span data-i18n="order_completed.total">Total</span>
                                <span>$<?= htmlspecialchars($order['total_price']) ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-footer bg-light p-4 text-center">
                    <p class="small text-muted mb-3">
                        <span data-i18n="order_completed.footer_note">We are currently picking your bricks. You will receive a tracking number once the package leaves our warehouse.</span>
                    </p>
                    <a href="index.php" class="btn btn-primary" data-i18n="order_completed.create_another">Create Another Mosaic</a>
                    <button onclick="window.print()" class="btn btn-outline-secondary" data-i18n="order_completed.print">Print Receipt</button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include("./includes/footer.php"); ?>
</body>
</html>

