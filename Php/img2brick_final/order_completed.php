<?php
session_start();
global $cnx;
include("./config/cnx.php");
require_once __DIR__ . '/includes/i18n.php';

if (!isset($_SESSION['userId'])) {
    header("Location: connexion.php");
    exit;
}

$userId = (int)$_SESSION['userId'];

$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
if ($orderId <= 0 && isset($_SESSION['last_order_id'])) {
    $orderId = (int)$_SESSION['last_order_id'];
}

if ($orderId <= 0) {
    header("Location: index.php");
    exit;
}

try {
    // Retrieve the order bill
    $stmt = $cnx->prepare("SELECT * FROM ORDER_BILL WHERE order_id = :oid AND user_id = :uid LIMIT 1");
    $stmt->execute(['oid' => $orderId, 'uid' => $userId]);
    $orderBill = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$orderBill || empty($orderBill['created_at'])) {
        header("Location: index.php"); // create error message
        exit;
    }

    // Retrieve the address linked to THIS order
    $stmt = $cnx->prepare("SELECT * FROM ADDRESS WHERE address_id = ?");
    $stmt->execute([$orderBill['address_id']]);
    $addr = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    // Retrieve user info
    $stmt = $cnx->prepare("SELECT first_name, last_name, email, phone FROM USER WHERE user_id = ?");
    $stmt->execute([$userId]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);

    // Calculation of total and list of items for email
    $stmt = $cnx->prepare("
        SELECT t.pavage_txt, i.path as lego_path
        FROM contain c
        JOIN TILLING t ON t.pavage_id = c.pavage_id
        JOIN IMAGE i ON t.image_id = i.image_id
        WHERE c.order_id = ?
    ");
    $stmt->execute([$orderId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalPrice = 0.0;
    $emailItemsHtml = "";

    foreach ($items as $item) {
        $stats = getTilingStats($item['pavage_txt']);
        $itemPrice = (float)$stats['price'] / 100;
        $totalPrice += $itemPrice;
        $emailItemsHtml .= "<li>LEGO mosaic ({$item['pavage_txt']}) - " . number_format($itemPrice, 2) . " EUR</li>";
    }

    $livraison = $totalPrice * 0.10;
    $totaux = $totalPrice + $livraison;

    // 5. Send email if not already sent
    if (!isset($_SESSION['mail_sent_' . $orderId])) {
        $subject = "Confirmation of your order #" . $orderId . " - Img2Brick";
        $body = "
            <h2>Thank you for your order, {$u['first_name']} !</h2>
            <p>We have received your payment. Here is the summary :</p>
            <ul>{$emailItemsHtml}</ul>
                <p><strong>Shipping costs :</strong> " . number_format($livraison, 2) . " EUR</p>
            <p><strong>Total paid :</strong> " . number_format($totaux, 2) . " EUR</p>
            <p>Your package will be shipped to the following address :<br>
            {$addr['street']}, {$addr['postal_code']} {$addr['city']}, {$addr['country']}</p>
        ";

        sendMail($u['email'], $subject, $body);
        $_SESSION['mail_sent_' . $orderId] = true; // avoid duplicates
    }

} catch (PDOException $e) {
    //die("System Error: " . $e->getMessage());
    die("System Error");
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
                        <h5 class="mb-0"><span data-i18n="order_completed.order_label">Order</span> #<?= htmlspecialchars((string)$orderBill['order_id']) ?></h5>
                        <span class="badge bg-warning text-dark status-badge">PREPARATION</span>
                    </div>
                </div>

                <div class="card-body p-4">
                    <div class="row g-4">
                        <div class="col-md-5 text-center border-end">
                            <h6 class="text-muted mb-3">Your Mosaic Preview</h6>
                            <?php if (!empty($items)): ?>
                                <img src="users/imgs/<?= htmlspecialchars($items[0]['lego_path']) ?>" class="lego-preview" alt="LEGO Result" style="width: 100%; max-width: 250px; border-radius: 8px;">
                            <?php endif; ?>
                        </div>
                        <div class="col-md-7">
                            <h6 class="text-muted border-bottom pb-2">Delivery Details</h6>
                            <p class="mb-1 fw-bold"><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></p>
                            <p class="text-muted small">Phone: <?= htmlspecialchars($u['phone']) ?></p>
                            <p>
                                <?= htmlspecialchars($addr['street'] ?? 'N/A') ?><br>
                                <?= htmlspecialchars(($addr['postal_code'] ?? '') . ' ' . ($addr['city'] ?? '')) ?><br>
                                <?= htmlspecialchars($addr['country'] ?? '') ?>
                            </p>

                            <h6 class="text-muted border-bottom pb-2 mt-4">Payment Summary</h6>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Subtotal</span>
                                <span><?= number_format($totalPrice, 2) ?> EUR</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Shipping</span>
                                <span><?= number_format($livraison, 2) ?> EUR</span>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between fs-5 fw-bold">
                                <span>Total</span>
                                <span><?= number_format($totaux, 2) ?> EUR</span>
                            </div>

                            <div class="mt-3 text-muted small">
                                Order date: <?= htmlspecialchars((string)$orderBill['created_at']) ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-footer bg-light p-4 text-center">
                    <p class="small text-muted mb-3">
                        <span data-i18n="order_completed.footer_note">
                            We are currently picking your bricks. You will receive a tracking number once the package leaves our warehouse.
                        </span>
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


