<?php
require_once __DIR__ . '/config/session.php';
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
        // To do : add a small picture of the result for each mosaics
        $emailItemsHtml .= "
        <li style='padding: 10px 0; border-bottom: 1px dashed #eee; list-style: none;'>
            <span style='color: #555;'>LEGO Mosaic</span> 
            <code style='background: #f4f4f4; padding: 2px 4px; font-size: 0.9em;'>({$item['pavage_txt']})</code>
            <span style='float: right; font-weight: bold;'>" . number_format($itemPrice, 2, ',', ' ') . " EUR</span>
        </li>";
    }

    $livraison = $totalPrice * 0.10;
    $totaux = $totalPrice + $livraison;

    // 5. Send email if not already sent
    if (!isset($_SESSION['mail_sent_' . $orderId])) {
        $body = "
            <div style='font-family: Arial, sans-serif; padding: 30px; border: 1px solid #eeeeee; border-radius: 10px; max-width: 600px; color: #333;'>
                <h2 style='color: #0d6efd; margin-top: 0;'>Thank you for your order, {$u['first_name']}!</h2>
                <p>We have received your payment and our team is getting your <strong>BrickMosaic</strong> package ready for shipment.</p>
                
                <div style='background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin: 25px 0;'>
                    <h3 style='margin-top: 0; font-size: 1.1em; border-bottom: 1px solid #dee2e6; padding-bottom: 10px;'>Order Summary</h3>
                    <ul style='list-style: none; padding: 0; margin: 15px 0;'>
                        {$emailItemsHtml}
                    </ul>
                    <div style='border-top: 1px solid #dee2e6; padding-top: 10px; margin-top: 10px;'>
                        <p style='margin: 5px 0; display: flex; justify-content: space-between;'>
                            <span>Shipping costs: </span> 
                            <strong>" . number_format($livraison, 2, ',', ' ') . " EUR</strong>
                        </p>
                        <p style='margin: 5px 0; font-size: 1.2em; color: #0d6efd;'>
                            <span>Total paid:</span> 
                            <strong>" . number_format($totaux, 2, ',', ' ') . " EUR</strong>
                        </p>
                    </div>
                </div>
            
                <div style='border: 1px solid #eee; padding: 20px; border-radius: 8px;'>
                    <h3 style='margin-top: 0; font-size: 1.1em; color: #666;'>Shipping Address</h3>
                    <p style='margin-bottom: 0; line-height: 1.6;'>
                        <strong>{$u['first_name']} {$u['last_name']}</strong><br>
                        {$addr['street']}<br>
                        {$addr['postal_code']} {$addr['city']}<br>
                        {$addr['country']}
                    </p>
                </div>
            
                <p style='margin-top: 30px; font-size: 0.9em; color: #999; text-align: center;'>
                    You will receive another email with your tracking number once your package is shipped.
                </p>
            </div>";

        sendMail($u['email'], "Order Confirmation #{$orderId} - BrickMosaic", $body);
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
    <title><?= htmlspecialchars(tr('order_completed.page_title', 'Order Confirmed - BrickMosaic')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
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
                                <span><?= number_format($totalPrice, 2, ',', ' ') ?> EUR</span>
                            </div>

                            <?php
                            $loyaltyRedeemed = $_SESSION['loyalty_redeemed'] ?? null;
                            if ($loyaltyRedeemed && !empty($loyaltyRedeemed['success'])):
                                $discPct = (int)$loyaltyRedeemed['discountPercent'];
                                $discAmt = round($totalPrice * $discPct / 100, 2);
                                $totalPrice -= $discAmt;
                                $livraison = $totalPrice * 0.10;
                                $totaux = $totalPrice + $livraison;
                            ?>
                            <div class="d-flex justify-content-between mb-2" style="color:#16a34a;">
                                <span>Reduction fidelite (<?= $discPct ?>%)</span>
                                <span>-<?= number_format($discAmt, 2, ',', ' ') ?> EUR</span>
                            </div>
                            <div class="text-muted small mb-2">
                                <?= (int)$loyaltyRedeemed['pointsDeducted'] ?> points utilises (reste: <?= (int)$loyaltyRedeemed['balanceAfter'] ?>)
                            </div>
                            <?php unset($_SESSION['loyalty_redeemed']); endif; ?>

                            <div class="d-flex justify-content-between mb-2">
                                <span>Shipping</span>
                                <span><?= number_format($livraison, 2, ',', ' ') ?> EUR</span>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between fs-5 fw-bold">
                                <span>Total</span>
                                <span><?= number_format($totaux, 2, ',', ' ') ?> EUR</span>
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


