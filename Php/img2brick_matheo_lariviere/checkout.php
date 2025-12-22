<?php
require_once "session.php";

if (!isset($_SESSION["user"])) {
    header("Location: log_out.php");
    exit;
}

$user = $_SESSION["user"];
$code_uti = (int)$user->get_id();

/* infos user */
$stmt = $pdo->prepare("SELECT nom, adresse_mail, numero_telephone FROM utilisateur WHERE code_uti = :id");
$stmt->execute([":id" => $code_uti]);
$uti = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$uti) { header("Location: index.php"); exit; }

/* panier + résumé */
$stmt = $pdo->prepare("SELECT id_img, nb FROM panier WHERE code_uti = :id");
$stmt->execute([":id" => $code_uti]);
$panier = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalQty = 0;
$subtotal = 0.0;

foreach ($panier as $p) {
    $id_img = (int)($p["id_img"] ?? 0);
    $nb     = (int)($p["nb"] ?? 0);
    if ($id_img <= 0 || $nb <= 0) continue;

    $stmt2 = $pdo->prepare("SELECT prix FROM img WHERE id_img = :id");
    $stmt2->execute([":id" => $id_img]);
    $prix = (float)($stmt2->fetchColumn() ?: 0);

    $totalQty += $nb;
    $subtotal += $prix * $nb;
}

$shipping = $subtotal * 0.10;
$total = $subtotal + $shipping;

$minDelivery = (new DateTime('today'))->modify('+3 days')->format('Y-m-d');

/* paiement simulé auto */
$card_number = "4242 4242 4242 4242";
$card_exp    = "12/34";
$card_cvc    = "123";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Order</title>
  <link rel="stylesheet" href="css/order.css">
</head>
<body>

<main class="wrap">
  <h1 class="page-title">Order</h1>

  <section class="card">
    <h2 class="card-title">Contact information</h2>

    <div class="grid-3">
      <div class="field">
        <label>Name</label>
        <input type="text" value="<?= htmlspecialchars($uti["nom"]) ?>" disabled>
      </div>

      <div class="field">
        <label>Email</label>
        <input type="email" value="<?= htmlspecialchars($uti["adresse_mail"]) ?>" disabled>
      </div>

      <div class="field">
        <label>Phone</label>
        <input type="text" value="<?= htmlspecialchars((string)$uti["numero_telephone"]) ?>" disabled>
      </div>
    </div>

    <div class="captcha-placeholder">CAPTCHA placeholder</div>
  </section>

  <!-- ✅ UN SEUL FORM, action order.php -->
  <form method="post" action="order.php" class="bottom-row">

    <div class="card">
      <h2 class="card-title">Payment & Summary</h2>

      <div class="notice">
        Simulated payment mode for the project (no real payment).
      </div>

      <div class="pay-grid">
        <div class="field">
          <label>Card number</label>
          <input type="text" name="card_number" value="<?= htmlspecialchars($card_number) ?>" required>
        </div>

        <div class="field">
          <label>Expiry (MM/YY)</label>
          <input type="text" name="card_exp" value="<?= htmlspecialchars($card_exp) ?>" required>
        </div>

        <div class="field">
          <label>CVC</label>
          <input type="text" name="card_cvc" value="<?= htmlspecialchars($card_cvc) ?>" required>
        </div>
      </div>

      <div class="summary">
        <div class="sum-row"><span>Items</span><strong><?= (int)$totalQty ?></strong></div>
        <div class="sum-row"><span>Subtotal</span><strong><?= number_format($subtotal, 2, ".", " ") ?> €</strong></div>
        <div class="sum-row"><span>Shipping (10%)</span><strong><?= number_format($shipping, 2, ".", " ") ?> €</strong></div>
        <div class="divider"></div>
        <div class="sum-row total"><span>Total</span><strong><?= number_format($total, 2, ".", " ") ?> €</strong></div>
      </div>
    </div>

    <div class="card">
      <h2 class="card-title">Delivery</h2>

      <div class="delivery-form">
        <div class="field">
          <label>Delivery address</label>
          <input type="text" name="delivery_address" required>
        </div>

        <div class="field">
          <label>Delivery date (min <?= htmlspecialchars($minDelivery) ?>)</label>
          <input type="date" name="delivery_date" min="<?= htmlspecialchars($minDelivery) ?>" required>
        </div>

        <button type="submit" class="btn-primary" <?= ($totalQty <= 0) ? "disabled" : "" ?>>
          Place order
        </button>

        <a class="btn-secondary" href="index.php">Back</a>
      </div>
    </div>

  </form>
</main>

</body>
</html>

