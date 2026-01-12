<?php
session_start();
global $cnx;
include("./config/cnx.php");

if (!isset($_SESSION['userId'])) {
    $_SESSION['redirect_after_login'] = 'order.php';
    header("Location: index.php");
    exit;
}

$userId = (int)$_SESSION['userId'];
$errors = [];

$totalPrice = isset($_SESSION['moneyea']) ? (float)$_SESSION['moneyea'] : 49.99;

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

    if (empty($errors)) {
        $cardNum = str_replace(' ', '', $_POST['card_number'] ?? '');
        $cardCvc = trim($_POST['card_cvc'] ?? '');

        if ($cardNum !== '4242424242424242' || $cardCvc !== '123') {
            $errors[] = "Payment Declined: Invalid Test Card Credentials.";
        }
    }

    if (empty($errors)) {
        try {
            $cnx->beginTransaction();

            // Create the fixed address for this order
            $stmt = $cnx->prepare("INSERT INTO ADDRESS (street, postal_code, city, country, user_id, is_default) VALUES (?, ?, ?, ?, ?, 0)");
            $stmt->execute([$street, $zip, $city, $country, $userId]);
            $orderAddressId = (int)$cnx->lastInsertId();

            // Update the user's default address
            $cnx->prepare("UPDATE ADDRESS SET is_default = 0 WHERE user_id = ? AND is_default = 1")->execute([$userId]);
            $stmt = $cnx->prepare("INSERT INTO ADDRESS (street, postal_code, city, country, user_id, is_default) VALUES (?, ?, ?, ?, ?, 1)");
            $stmt->execute([$street, $zip, $city, $country, $userId]);

            // Update the user's infos
            $stmt = $cnx->prepare("UPDATE USER SET first_name = ?, last_name = ?, phone = ? WHERE user_id = ?");
            $stmt->execute([$fName, $lName, $phone, $userId]);

            // Validate order
            $stmt = $cnx->prepare("UPDATE ORDER_BILL SET created_at = NOW(), address_id = ? WHERE order_id = ?");
            $stmt->execute([$orderAddressId, $cartOrderId]);

            $cnx->commit();

            $_SESSION['last_order_id'] = $cartOrderId;
            addLog($cnx, "USER", "CONFIRM", "order");
            header("Location: order_completed.php?order_id=" . $cartOrderId);
            exit;
        } catch (Exception $e) {
            $cnx->rollBack();
            //$errors[] = "System Error: " . $e->getMessage();
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
    $stats = getTilingStats($txt); // Utilise la fonction définie dans cnx.php
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
    <title>Checkout - Img2Brick</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
    </style>
</head>

<body>

    <div class="container bg-light py-5">
        <div class="row justify-content-center">
            <div class="col-12 col-lg-8">

                <h4 class="mb-3">Checkout</h4>

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
                    <input type="hidden" name="csrf" value="<?= csrf_get() ?>">

                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-white fw-bold">1. Contact Details</div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">First Name</label>
                                    <input type="text" class="form-control" name="first_name" value="<?= htmlspecialchars($fillName) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Last Name</label>
                                    <input type="text" class="form-control" name="last_name" value="<?= htmlspecialchars($fillSurname) ?>" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" name="phone" value="<?= htmlspecialchars($fillPhone) ?>" required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-white fw-bold">2. Shipping Address</div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">Address</label>
                                    <input type="text" class="form-control" name="address" value="<?= htmlspecialchars($fillAddr) ?>" required>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label">Country</label>
                                    <select class="form-select" name="country" required>
                                        <option value="France" <?= ($fillCountry === 'France' ? 'selected' : '') ?>>France</option>
                                        <option value="USA" <?= ($fillCountry === 'USA' ? 'selected' : '') ?>>United States</option>
                                        <option value="UK" <?= ($fillCountry === 'UK' ? 'selected' : '') ?>>United Kingdom</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">City</label>
                                    <input type="text" class="form-control" name="city" value="<?= htmlspecialchars($fillCity) ?>" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Zip</label>
                                    <input type="text" class="form-control" name="zip" value="<?= htmlspecialchars($fillZip) ?>" required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-white fw-bold">3. Payment</div>
                        <div class="card-body">
                            <div class="payment-warning mb-3 text-center">
                                ⚠️ <strong>SIMULATED PAYMENT MODE</strong><br>No real money charged.
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Name on card</label>
                                    <input type="text" class="form-control" name="card_name" value="John Placeholder" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Card number</label>
                                    <input type="text" class="form-control" name="card_number" value="4242 4242 4242 4242" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Expiration</label>
                                    <input type="text" class="form-control" name="card_exp" value="12/34" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">CVC</label>
                                    <input type="text" class="form-control" name="card_cvc" value="123" required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-white fw-bold">4. Order Summary</div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <span>Subtotal:</span>
                                <span><?= number_format($totalPrice, 2) ?> EUR</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Shipping (10%):</span>
                                <span><?= number_format($livraison, 2) ?> EUR</span>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between fw-bold fs-5">
                                <span>Total:</span>
                                <span><?= number_format($totaux, 2) ?> EUR</span>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4 d-flex justify-content-center">
                        <div class="cf-turnstile" data-sitekey="<?= htmlspecialchars($_ENV['CLOUDFLARE_TURNSTILE_PUBLIC'] ?? '') ?>"></div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
                        <a href="cart.php" class="btn btn-outline-secondary">← Back to Cart</a>
                        <button class="btn btn-primary btn-lg" type="submit" name="confirm_order">
                            Confirm Order ($<?= number_format($totaux, 2) ?>)
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>

    <?php include("./includes/footer.php"); ?>
</body>

</html>