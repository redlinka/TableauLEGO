<?php
session_start();
require_once "connection.inc.php";
require_once "src/UserManager.php";

$cnx = getConnection();
$userMgr = new UserManager($cnx);

if (isset($_POST['choice'])) {
    $_SESSION['order_filter'] = $_POST['choice'];
}
if (isset($_POST['picture_id'])) {
    $_SESSION['order_picture_id'] = $_POST['picture_id'];
}
if (isset($_POST['size'])) {
    $_SESSION['order_size'] = intval($_POST['size']);
}

// 1. Recovery of data from result.php
$pictureId = $_SESSION['order_picture_id'] ?? null;
$choice    = $_SESSION['order_filter'] ?? null;
$size      = $_SESSION['order_size'] ?? null;

if (!$pictureId || !$choice || !$size) {
    header("Location: index.php");
    exit;
}

// 2. Fetch picture data
$stmt = $cnx->prepare("SELECT image_data, fileextension FROM picture WHERE id = :id");
$stmt->bindParam(":id", $pictureId);
$stmt->execute();
$picture = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$picture) {
    header("Location: index.php?error=notfound");
    exit;
}

$base64 = base64_encode($picture['image_data']);
$mimeType = ($picture['fileextension'] === 'png') ? 'image/png' : 'image/jpeg';

// 3. Price Calculation
$coefficients = ['blue' => 0.15, 'red' => 0.12, 'bw' => 0.10];
$unitPrice = $coefficients[$choice] ?? 0.10;
$totalPrice = number_format($size * $unitPrice, 2);

// 4. Fetch User Data if logged in
$user = null;
if (isset($_SESSION["user_id"])) {
    $user = $userMgr->getUserById($_SESSION["user_id"]);
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Img2Brick</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="p-order">
<?php include "header.inc.php"; ?>

<div class="container">
    <form action="order_confirmation.php" method="POST">
        <input type="hidden" name="picture_id" value="<?= $pictureId ?>">
        <input type="hidden" name="filter" value="<?= $choice ?>">
        <input type="hidden" name="size" value="<?= $size ?>">
        <input type="hidden" name="amount" value="<?= $totalPrice ?>">

        <header class="page-title">
            <h1>Complete Your Order</h1>
            <p>Verify your details and finalize your brick mosaic purchase.</p>
        </header>

        <div class="checkout-grid">
            <div class="order-forms">

                <?php if (!$user): ?>
                    <section class="checkout-section">
                        <div class="section-header">
                            <span class="step-number">1</span>
                            <h3>User Account</h3>
                        </div>
                        <?php if (isset($_GET['error']) && $_GET['error'] === 'password_weak'): ?>
                            <div class="alert alert-error">Password is too weak. Min 12 chars + mixed case/numbers/symbols.</div>
                        <?php endif; ?>
                        <p class="login-prompt">Already have an account? <a href="connection.php">Log in here</a></p>

                        <div class="form-group">
                            <label for="name">Full Name</label>
                            <input type="text" name="name" id="name" placeholder="John Doe" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" name="email" id="email" placeholder="john@example.com" required>
                        </div>
                        <div class="form-group">
                            <label for="pwd">Create Password</label>
                            <input type="password" name="pwd" id="pwd" placeholder="Choose a secure password" required>
                        </div>
                        <div class="frc-captcha" data-sitekey="FCMV6VL3726JO53T"></div>
                    </section>
                <?php endif; ?>

                <section class="checkout-section">
                    <div class="section-header">
                        <span class="step-number"><?= $user ? '1' : '2' ?></span>
                        <h3>Shipping Information</h3>
                    </div>
                    <div class="form-group">
                        <label for="address">Full Address</label>
                        <textarea name="address" id="address" placeholder="Street name and number" required><?= htmlspecialchars($user['shipping_address'] ?? '') ?></textarea>
                    </div>
                    <div class="row">
                        <div class="form-group">
                            <label for="zip">Postal Code</label>
                            <input type="text" name="zip" id="zip" placeholder="Postal Code" required>
                        </div>
                        <div class="form-group">
                            <label for="city">City</label>
                            <input type="text" name="city" id="city" placeholder="City" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="tel" name="phone" id="phone" placeholder="01 23 34 56 78" required>
                    </div>
                </section>

                <section class="checkout-section">
                    <div class="section-header">
                        <span class="step-number"><?= $user ? '2' : '3' ?></span>
                        <h3>Payment</h3>
                    </div>
                    <div class="mock-alert">
                        <i class="fa-solid fa-flask"></i> Simulation mode: No real payment will be processed.
                    </div>
                    <div class="form-group">
                        <label>Card Number</label>
                        <input type="text" value="4242 4242 4242 4242" readonly class="readonly-input">
                    </div>
                    <div class="row">
                        <div class="form-group">
                            <label>Expiry Date</label>
                            <input type="text" value="12/34" readonly class="readonly-input small">
                        </div>
                        <div class="form-group">
                            <label>CVC</label>
                            <input type="text" value="123" readonly class="readonly-input small">
                        </div>
                    </div>
                </section>
            </div>

            <aside class="order-summary">
                <div class="summary-card">
                    <h3>Order Summary</h3>
                    <div class="preview-container">
                        <img class="filter-<?= $choice ?>"
                             src="data:<?= $mimeType ?>;base64,<?= $base64 ?>"
                             alt="Mosaic Preview">
                    </div>
                    <div class="summary-details">
                        <div class="summary-row">
                            <span>Variant:</span>
                            <strong><?= ucfirst($choice) ?></strong>
                        </div>
                        <div class="summary-row">
                            <span>Resolution:</span>
                            <strong><?= $size ?> x <?= $size ?></strong>
                        </div>
                        <div class="summary-row">
                            <span>Bricks count:</span>
                            <strong><?= $size * $size ?></strong>
                        </div>
                    </div>
                    <div class="summary-total">
                        <span>Final Total:</span>
                        <strong><?= $totalPrice ?> €</strong>
                    </div>
                    <button type="submit" class="btn-confirm">
                        Place My Order
                    </button>
                </div>
            </aside>
        </div>
    </form>
</div>

<script type="module" src="https://cdn.jsdelivr.net/npm/@friendlycaptcha/sdk@0.1.31/site.min.js" async defer></script>
<?php include "footer.inc.php"; ?>
</body>
</html>