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

$userId = (int)$_SESSION['userId'];

// ✅ On accepte l'id via GET (recommandé), sinon fallback session
$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
if ($orderId <= 0 && isset($_SESSION['last_order_id'])) {
    $orderId = (int)$_SESSION['last_order_id'];
}

if ($orderId <= 0) {
    header("Location: index.php");
    exit;
}

try {
    // 1) Récupérer la commande (ORDER_BILL)
    $stmt = $cnx->prepare("
        SELECT order_id, user_id, created_at, address_id
        FROM ORDER_BILL
        WHERE order_id = :oid AND user_id = :uid
        LIMIT 1
    ");
    $stmt->execute(['oid' => $orderId, 'uid' => $userId]);
    $orderBill = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$orderBill) {
        die("Order not found or access denied.");
    }

    // Sécurité : si created_at est NULL, ce n'est pas une commande validée
    if (empty($orderBill['created_at'])) {
        header("Location: cart.php");
        exit;
    }

    $addressId = !empty($orderBill['address_id']) ? (int)$orderBill['address_id'] : 0;

    // 2) Récupérer user
    $stmt = $cnx->prepare("
        SELECT first_name, last_name, phone
        FROM USER
        WHERE user_id = :uid
        LIMIT 1
    ");
    $stmt->execute(['uid' => $userId]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    // 3) Récupérer adresse (si liée)
    $addr = [
        'street' => '',
        'postal_code' => '',
        'city' => '',
        'country' => ''
    ];

    if ($addressId > 0) {
        $stmt = $cnx->prepare("
            SELECT street, postal_code, city, country
            FROM ADDRESS
            WHERE address_id = :aid AND user_id = :uid
            LIMIT 1
        ");
        $stmt->execute(['aid' => $addressId, 'uid' => $userId]);
        $a = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($a) $addr = $a;
    }

    // 4) Image preview optionnelle via contain -> tilling -> image
    $previewSrc = 'images/placeholder.png'; // adapte si besoin

    $stmt = $cnx->prepare("
        SELECT i.path, i.filename
        FROM contain c
        LEFT JOIN TILLING t ON t.pavage_id = c.pavage_id
        LEFT JOIN IMAGE i ON i.image_id = t.image_id
        WHERE c.order_id = :oid
        LIMIT 1
    ");
    $stmt->execute(['oid' => $orderId]);
    $img = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!empty($img['path']) && !empty($img['filename'])) {
        $path = rtrim(str_replace('\\', '/', $img['path']), '/') . '/';
        if ($path !== '' && $path[0] !== '/') $path = '/' . $path;
        $previewSrc = $path . $img['filename'];
    }

    // 5) Statut (dans ta BDD, ORDER_BILL n'a pas status => valeur fixe)
    $orderStatus = 'PREPARATION';

    // 6) Prix (tu le stockes ailleurs ? sinon session ou fixe)

    $stmt = $cnx->prepare("
        SELECT t.pavage_txt
        FROM contain c
        JOIN TILLING t ON t.pavage_id = c.pavage_id
        WHERE c.order_id = :oid
    ");
    $stmt->execute(['oid' => $orderId]);
    $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $total = 0.0;

    foreach ($rows as $txt) {
        if (preg_match('/^\d+(\.\d+)?/', $txt, $m)) {
            $total += (float)$m[0]/100;
        }
    }

    $totalPrice = $total;
    $livraison = $total*0.10;
    $totaux = $livraison + $totalPrice;

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
                        <h5 class="mb-0"><span data-i18n="order_completed.order_label">Order</span> #<?= htmlspecialchars((string)$orderBill['order_id']) ?></h5>
                        <?php
                        $statusClass = ($orderStatus === 'PREPARATION') ? 'bg-warning text-dark' : 'bg-primary text-white';
                        $statusMap = [
                            'PREPARATION' => 'orders.status.preparation',
                            'SHIPPED' => 'orders.status.shipped',
                            'DELIVERED' => 'orders.status.delivered',
                            'CANCELLED' => 'orders.status.cancelled',
                        ];
                        $statusKey = $statusMap[$orderStatus] ?? null;
                        $statusLabel = $statusKey ? tr($statusKey, $orderStatus) : $orderStatus;
                        ?>
                        <span class="badge <?= $statusClass ?> status-badge">
                            <?= htmlspecialchars($statusLabel) ?>
                        </span>
                    </div>
                </div>

                <div class="card-body p-4">
                    <div class="row g-4">

                        

                        <div class="col-md-7">
                            <h6 class="text-muted border-bottom pb-2" data-i18n="order_completed.delivery">Delivery Details</h6>

                            <p class="mb-1 fw-bold">
                                <?= htmlspecialchars(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')) ?>
                            </p>
                            <p class="mb-3 text-muted small">
                                <?= htmlspecialchars(tr('order_completed.phone_label', 'Phone:')) ?>
                                <?= htmlspecialchars($u['phone'] ?? '') ?>
                            </p>

                            <p class="mb-4">
                                <?= nl2br(htmlspecialchars(
                                    trim(
                                        ($addr['street'] ?? '') . "\n" .
                                        ($addr['postal_code'] ?? '') . ' ' . ($addr['city'] ?? '') . "\n" .
                                        ($addr['country'] ?? '')
                                    )
                                )) ?>
                            </p>

                            <h6 class="text-muted border-bottom pb-2" data-i18n="order_completed.payment">Payment Summary</h6>
                            <div class="d-flex justify-content-between mb-2">
                                <span data-i18n="order_completed.kit">Mosaic Kit</span>
                                <span>$<?= htmlspecialchars(number_format($totalPrice, 2)) ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span data-i18n="order_completed.shipping">Shipping</span>
                                <span>$<?= htmlspecialchars(number_format($livraison, 2)) ?></span>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between fs-5 fw-bold">
                                <span data-i18n="order_completed.total">Total</span>
                                <span>$<?= htmlspecialchars(number_format($totaux, 2)) ?></span>
                            </div>

                            <div class="mt-3 text-muted small">
                                <?= htmlspecialchars(tr('order_completed.date_label', 'Order date:')) ?>
                                <?= htmlspecialchars((string)$orderBill['created_at']) ?>
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


