<?php
require_once __DIR__ . '/config/session.php';
global $cnx;
include("./config/cnx.php");
require_once __DIR__ . '/config/games_api.php';
require_once __DIR__ . '/includes/i18n.php';

if (!isset($_SESSION['userId'])) {
    $_SESSION['redirect_after_login'] = 'order.php';
    header("Location: index.php");
    exit;
}

$userId = (int)$_SESSION['userId'];
$errors = [];

$isLocal = in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1'], true)
    || str_starts_with($_SERVER['HTTP_HOST'] ?? '', 'localhost')
    || str_starts_with($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1');

$stmt = $cnx->prepare("
    SELECT order_id, address_id
    FROM ORDER_BILL
    WHERE user_id = :uid
      AND created_at IS NULL
    LIMIT 1
");
$stmt->execute(['uid' => $userId]);
$cart = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cart) {
    header("Location: cart.php");
    exit;
}

$cartOrderId   = (int)$cart['order_id'];
$cartAddressId = !empty($cart['address_id']) ? (int)$cart['address_id'] : 0;

$stmt = $cnx->prepare("
    SELECT first_name, last_name, phone
    FROM USER
    WHERE user_id = :uid
    LIMIT 1
");
$stmt->execute(['uid' => $userId]);
$u = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$fillName    = $u['first_name'] ?? '';
$fillSurname = $u['last_name'] ?? '';
$fillPhone   = $u['phone'] ?? '';

$stmt = $cnx->prepare("
    SELECT street, postal_code, city, country
    FROM ADDRESS
    WHERE user_id = ? and is_default = 1
    LIMIT 1
");
$stmt->execute([$userId]);
$defaultAddr = $stmt->fetch(PDO::FETCH_ASSOC);

$fillAddr    = $defaultAddr['street'] ?? '';
$fillZip     = $defaultAddr['postal_code'] ?? '';
$fillCity    = $defaultAddr['city'] ?? '';
$fillCountry = $defaultAddr['country'] ?? 'France';


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_order'])) {

    if (!csrf_validate($_POST['csrf'] ?? '')) {
        $errors[] = "Invalid security token (CSRF). Please try again.";
    }

    $fName   = trim($_POST['first_name'] ?? '');
    $lName   = trim($_POST['last_name'] ?? '');
    $phone   = trim($_POST['phone'] ?? '');

    $street  = trim($_POST['address'] ?? '');
    $city    = trim($_POST['city'] ?? '');
    $zip     = trim($_POST['zip'] ?? '');
    $country = trim($_POST['country'] ?? '');

    $fillName    = $fName;
    $fillSurname = $lName;
    $fillPhone   = $phone;
    $fillAddr    = $street;
    $fillCity    = $city;
    $fillZip     = $zip;
    $fillCountry = $country;

    if (empty($errors) && !$isLocal) {
        $token = $_POST['cf-turnstile-response'] ?? '';
        if ($token === '') {
            $errors[] = "Please complete the captcha.";
        } else {
            set_error_handler(function () {
                return true;
            });
            $ts = validateTurnstile();
            restore_error_handler();
            if (empty($ts['success'])) {
                $errors[] = "Captcha failed.";
            }
        }
    }

    if (empty($errors)) {
        if ($fName === '' || $lName === '' || $phone === '' || $street === '' || $city === '' || $zip === '' || $country === '') {
            $errors[] = "Please fill in all contact and shipping fields.";
        }
    }

    // Payment validation
    $paymentMethod = $_POST['payment_method'] ?? 'card';

    if (empty($errors) && $paymentMethod === 'card') {
        $cardNum = str_replace(' ', '', $_POST['card_number'] ?? '');
        $cardCvc = trim($_POST['card_cvc'] ?? '');

        if ($cardNum !== '4242424242424242' || $cardCvc !== '123') {
            $errors[] = "Payment Declined: Invalid Test Card Credentials.";
        }
    }

    if (empty($errors) && $paymentMethod === 'paypal') {
        // PayPal sandbox - simulated acceptance
        $paypalEmail = trim($_POST['paypal_email'] ?? '');
        if (empty($paypalEmail) || !filter_var($paypalEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Please enter a valid PayPal email address.";
        }
    }

    if (empty($errors)) {
        // Validate & redeem loyalty BEFORE committing the order
        $loyaltyTier = (int)($_POST['loyalty_tier'] ?? $_SESSION['loyalty_tier'] ?? 0);
        $validTiers = [0, 200, 500, 1000];
        if (!in_array($loyaltyTier, $validTiers, true)) {
            $loyaltyTier = 0;
        }

        $redeemResult = null;
        if ($loyaltyTier > 0) {
            $gamesToken = games_exchange_token($userId, $_SESSION['username'] ?? '');
            if ($gamesToken) {
                $redeemResult = games_redeem_points($gamesToken, $loyaltyTier, 'ORDER-PENDING-' . $cartOrderId);
            }
            if (!$redeemResult || empty($redeemResult['success'])) {
                $errors[] = "La deduction de vos points de fidelite a echoue. Votre solde est peut-etre insuffisant. Veuillez reessayer sans reduction ou verifier vos points.";
                $loyaltyTier = 0;
            }
        }
    }

    if (empty($errors)) {
        try {
            $cnx->beginTransaction();

            $stmt = $cnx->prepare("INSERT INTO ADDRESS (street, postal_code, city, country, user_id, is_default) VALUES (?, ?, ?, ?, ?, 0)");
            $stmt->execute([$street, $zip, $city, $country, $userId]);
            $orderAddressId = (int)$cnx->lastInsertId();

            $cnx->prepare("UPDATE ADDRESS SET is_default = 0 WHERE user_id = ? AND is_default = 1")->execute([$userId]);
            $stmt = $cnx->prepare("INSERT INTO ADDRESS (street, postal_code, city, country, user_id, is_default) VALUES (?, ?, ?, ?, ?, 1)");
            $stmt->execute([$street, $zip, $city, $country, $userId]);

            $stmt = $cnx->prepare("UPDATE USER SET first_name = ?, last_name = ?, phone = ? WHERE user_id = ?");
            $stmt->execute([$fName, $lName, $phone, $userId]);

            $discountPercent = $redeemResult ? (float)$redeemResult['discountPercent'] : null;
            $loyaltyPointsUsed = $redeemResult ? (int)$redeemResult['pointsDeducted'] : null;

            $stmt = $cnx->prepare("UPDATE ORDER_BILL SET created_at = NOW(), address_id = ?, discount_percent = ?, loyalty_points_used = ? WHERE order_id = ?");
            $stmt->execute([$orderAddressId, $discountPercent, $loyaltyPointsUsed, $cartOrderId]);

            $cnx->commit();

            if ($redeemResult) {
                $_SESSION['loyalty_redeemed'] = $redeemResult;
            }
            unset($_SESSION['loyalty_tier']);

            $_SESSION['last_order_id'] = $cartOrderId;
            addLog($cnx, "USER", "CONFIRM", "order");
            header("Location: order_completed.php?order_id=" . $cartOrderId);
            exit;
        } catch (Exception $e) {
            $cnx->rollBack();
        }
    }
}

$stmt = $cnx->prepare("
        SELECT t.pavage_txt
        FROM contain c
        JOIN TILLING t ON t.pavage_id = c.pavage_id
        WHERE c.order_id = :oid
    ");
$stmt->execute(['oid' => $cartOrderId]);
$rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
if (empty($rows)) {
    header("Location: cart.php?error=empty_cart");
    exit;
}

$total = 0.0;
foreach ($rows as $txt) {
    $stats = getTilingStats($txt);
    if (isset($stats['price'])) {
        $total += (float)$stats['price'] / 100;
    }
}

$totalPrice = $total;
$livraison = $total * 0.10;
$totaux = $livraison + $totalPrice;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(tr('order.page_title', 'Checkout - Img2Brick')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    <style>
        .payment-warning {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
            border-radius: 4px;
            padding: 10px;
            font-size: .9rem;
        }
        .payment-tab { cursor: pointer; }
        .payment-tab.active { background-color: #0d6efd !important; color: white !important; }
        .payment-content { display: none; }
        .payment-content.active { display: block; }
        .coupon-result { font-size: 0.9rem; margin-top: 6px; }
    </style>
</head>

<body>

    <?php include("./includes/navbar.php"); ?>

    <div class="container bg-light py-5">
        <div class="row justify-content-center">
            <div class="col-12 col-lg-8">

                <h4 class="mb-3" data-i18n="order.title">Checkout</h4>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $e): ?>
                                <li><?= htmlspecialchars($e) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" novalidate>
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_get()) ?>">

                    <!-- 1. Contact Details -->
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-white fw-bold" data-i18n="order.contact">1. Contact Details</div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" data-i18n="order.first_name">First Name</label>
                                    <input type="text" class="form-control" name="first_name" value="<?= htmlspecialchars($fillName) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" data-i18n="order.last_name">Last Name</label>
                                    <input type="text" class="form-control" name="last_name" value="<?= htmlspecialchars($fillSurname) ?>" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label" data-i18n="order.phone">Phone Number</label>
                                    <input type="tel" class="form-control" name="phone" value="<?= htmlspecialchars($fillPhone) ?>" required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 2. Shipping Address -->
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-white fw-bold" data-i18n="order.shipping">2. Shipping Address</div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label" data-i18n="order.address">Address</label>
                                    <input type="text" class="form-control" name="address" value="<?= htmlspecialchars($fillAddr) ?>" required>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label" data-i18n="order.country">Country</label>
                                    <select class="form-select" name="country" required>
                                        <option value="France" <?= ($fillCountry === 'France' ? 'selected' : '') ?>>France</option>
                                        <option value="USA" <?= ($fillCountry === 'USA' ? 'selected' : '') ?>>United States</option>
                                        <option value="UK" <?= ($fillCountry === 'UK' ? 'selected' : '') ?>>United Kingdom</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" data-i18n="order.city">City</label>
                                    <input type="text" class="form-control" name="city" value="<?= htmlspecialchars($fillCity) ?>" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label" data-i18n="order.zip">Zip</label>
                                    <input type="text" class="form-control" name="zip" value="<?= htmlspecialchars($fillZip) ?>" required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 3. Coupon Code -->
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-white fw-bold" data-i18n="order.coupon_title">3. Coupon Code (Optional)</div>
                        <div class="card-body">
                            <div class="input-group">
                                <input type="text" class="form-control" id="couponInput" placeholder="Enter coupon code" data-i18n-attr="placeholder:order.coupon_placeholder">
                                <button class="btn btn-outline-primary" type="button" id="applyCouponBtn" data-i18n="order.apply_coupon">Apply</button>
                            </div>
                            <div id="couponResult" class="coupon-result"></div>
                            <input type="hidden" name="coupon_code" id="couponCode" value="">
                            <input type="hidden" name="coupon_discount" id="couponDiscount" value="0">
                        </div>
                    </div>

                    <!-- 4. Payment -->
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-white fw-bold" data-i18n="order.payment">4. Payment</div>
                        <div class="card-body">
                            <div class="payment-warning mb-3 text-center">
                                <strong>SIMULATED PAYMENT MODE</strong><br>No real money charged.
                            </div>

                            <!-- Payment method tabs -->
                            <div class="btn-group w-100 mb-3" role="group">
                                <button type="button" class="btn btn-outline-secondary payment-tab active" onclick="switchPayment('card')">
                                    Credit Card
                                </button>
                                <button type="button" class="btn btn-outline-secondary payment-tab" onclick="switchPayment('paypal')">
                                    PayPal (Sandbox)
                                </button>
                            </div>
                            <input type="hidden" name="payment_method" id="paymentMethod" value="card">

                            <!-- Card payment -->
                            <div id="cardPayment" class="payment-content active">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label" data-i18n="order.card_name">Name on card</label>
                                        <input type="text" class="form-control" name="card_name" value="John Placeholder">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label" data-i18n="order.card_number">Card number</label>
                                        <input type="text" class="form-control" name="card_number" value="4242 4242 4242 4242">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label" data-i18n="order.card_exp">Expiration</label>
                                        <input type="text" class="form-control" name="card_exp" value="12/34">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">CVC</label>
                                        <input type="text" class="form-control" name="card_cvc" value="123">
                                    </div>
                                </div>
                            </div>

                            <!-- PayPal sandbox -->
                            <div id="paypalPayment" class="payment-content">
                                <div class="alert alert-info">
                                    <strong>PayPal Sandbox Mode</strong><br>
                                    Enter any valid email to simulate a PayPal payment.
                                </div>
                                <label class="form-label">PayPal Email</label>
                                <input type="email" class="form-control" name="paypal_email" placeholder="buyer@sandbox.paypal.com">
                            </div>
                        </div>
                    </div>

                    <!-- 5. Order Summary -->
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-white fw-bold" data-i18n="order.summary">5. Order Summary</div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <span data-i18n="order.subtotal">Subtotal:</span>
                                <span><?= number_format($totalPrice, 2, ',', ' ') ?> EUR</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span data-i18n="order.shipping_fee">Shipping (10%):</span>
                                <span><?= number_format($livraison, 2, ',', ' ') ?> EUR</span>
                            </div>
                            <div class="d-flex justify-content-between" id="couponLine" style="display:none !important;">
                                <span class="text-success" data-i18n="order.coupon_discount">Coupon discount:</span>
                                <span class="text-success" id="couponAmountDisplay">-0,00 EUR</span>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between fw-bold fs-5">
                                <span data-i18n="order.total">Total:</span>
                                <span id="totalDisplay"><?= number_format($totaux, 2, ',', ' ') ?> EUR</span>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4 d-flex justify-content-center">
                        <div class="cf-turnstile" data-sitekey="<?= htmlspecialchars($_ENV['CLOUDFLARE_TURNSTILE_PUBLIC'] ?? '') ?>"></div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
                        <a href="cart.php" class="btn btn-outline-secondary" data-i18n="order.back_cart">Back to Cart</a>
                        <button class="btn btn-primary btn-lg" type="submit" name="confirm_order" data-i18n="order.confirm">
                            Confirm Order (<?= number_format($totaux, 2, ',', ' ') ?> EUR)
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>

    <?php include("./includes/footer.php"); ?>

    <script>
        // Payment method switching
        function switchPayment(method) {
            document.getElementById('paymentMethod').value = method;
            document.querySelectorAll('.payment-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.payment-content').forEach(c => c.classList.remove('active'));

            if (method === 'card') {
                document.querySelector('[onclick="switchPayment(\'card\')"]').classList.add('active');
                document.getElementById('cardPayment').classList.add('active');
            } else {
                document.querySelector('[onclick="switchPayment(\'paypal\')"]').classList.add('active');
                document.getElementById('paypalPayment').classList.add('active');
            }
        }

        // Coupon logic
        const baseTotal = <?= $totaux ?>;
        const baseSubtotal = <?= $totalPrice ?>;
        const baseShipping = <?= $livraison ?>;

        document.getElementById('applyCouponBtn').addEventListener('click', function() {
            const code = document.getElementById('couponInput').value.trim();
            if (!code) return;

            fetch('api/coupon.php?code=' + encodeURIComponent(code))
                .then(r => r.json())
                .then(data => {
                    const result = document.getElementById('couponResult');
                    if (data.valid) {
                        result.innerHTML = '<span class="text-success fw-bold">' + data.label + ' applied!</span>';
                        document.getElementById('couponCode').value = data.code;
                        document.getElementById('couponDiscount').value = data.discount;

                        let discount = 0;
                        if (data.type === 'percent') {
                            discount = baseSubtotal * data.discount / 100;
                        } else {
                            discount = data.discount;
                        }

                        const newTotal = Math.max(0, baseTotal - discount);
                        document.getElementById('couponLine').style.display = 'flex';
                        document.getElementById('couponLine').style.setProperty('display', 'flex', 'important');
                        document.getElementById('couponAmountDisplay').textContent = '-' + discount.toFixed(2).replace('.', ',') + ' EUR';
                        document.getElementById('totalDisplay').textContent = newTotal.toFixed(2).replace('.', ',') + ' EUR';
                    } else {
                        result.innerHTML = '<span class="text-danger">' + (data.error || 'Invalid code') + '</span>';
                    }
                })
                .catch(() => {
                    document.getElementById('couponResult').innerHTML = '<span class="text-danger">Error checking coupon</span>';
                });
        });
    </script>
</body>

</html>
