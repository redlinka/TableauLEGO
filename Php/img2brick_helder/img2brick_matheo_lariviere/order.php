<?php
require_once "session.php";

if (!isset($_SESSION["user"])) {
    header("Location: log_out.php");
    exit;
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$user = $_SESSION["user"];
$code_uti = (int)$user->get_id();


$stmt = $pdo->prepare("SELECT nom, adresse_mail, numero_telephone FROM utilisateur WHERE code_uti = :id");
$stmt->execute([":id" => $code_uti]);
$uti = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$uti) {
    header("Location: index.php");
    exit;
}


$minDelivery = (new DateTime('today'))->modify('+3 days')->format('Y-m-d');


$card_number = "4242 4242 4242 4242";
$card_exp    = "12/34";
$card_cvc    = "123";

function load_cart(PDO $pdo, int $code_uti): array {
    $stmt = $pdo->prepare("SELECT id_img, nb FROM panier WHERE code_uti = :id");
    $stmt->execute([":id" => $code_uti]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function compute_totals(PDO $pdo, array $panier): array {
    $totalQty = 0;
    $subtotal = 0.0;

    foreach ($panier as $p) {
        $id_img = (int)($p["id_img"] ?? 0);
        $nb     = (int)($p["nb"] ?? 0);
        if ($id_img <= 0 || $nb <= 0) continue;

        $stmt = $pdo->prepare("SELECT prix FROM img WHERE id_img = :id");
        $stmt->execute([":id" => $id_img]);
        $prix = (float)($stmt->fetchColumn() ?: 0);

        $totalQty += $nb;
        $subtotal += $prix * $nb;
    }

    $shipping = $subtotal * 0.10;
    $total = $subtotal + $shipping;

    return [$totalQty, $subtotal, $shipping, $total];
}

$panier = load_cart($pdo, $code_uti);
[$totalQty, $subtotal, $shipping, $total] = compute_totals($pdo, $panier);


$error = null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $adresse = trim($_POST["delivery_address"] ?? "");
    $dateLiv = $_POST["delivery_date"] ?? "";

    $card_number = trim($_POST["card_number"] ?? "");
    $card_exp    = trim($_POST["card_exp"] ?? "");
    $card_cvc    = trim($_POST["card_cvc"] ?? "");

  
    if ($totalQty <= 0) {
        $error = "Your cart is empty.";
    } elseif ($adresse === "" || $dateLiv === "") {
        $error = "Missing delivery address or delivery date.";
    } else {
        $d = DateTime::createFromFormat('Y-m-d', $dateLiv);
        if (!$d) {
            $error = "Invalid delivery date.";
        } elseif ($dateLiv < $minDelivery) {
            $error = "Delivery date must be at least 3 days from today.";
        }
    }

    if ($error === null) {
        if (function_exists("simulated_payment_ok")) {
            if (!simulated_payment_ok($card_number, $card_exp, $card_cvc)) {
                $error = "Simulated payment failed. Use 4242 4242 4242 4242 / 12/34 / 123";
            }
        } else {
            $clean = preg_replace('/\s+/', '', $card_number);
            if (!($clean === "4242424242424242" && $card_exp === "12/34" && $card_cvc === "123")) {
                $error = "Simulated payment failed. Use 4242 4242 4242 4242 / 12/34 / 123";
            }
        }
    }

    if ($error === null) {
        try {
            $pdo->beginTransaction();


            $panier2 = load_cart($pdo, $code_uti);
            [$qty2, $sub2, $ship2, $tot2] = compute_totals($pdo, $panier2);

            if ($qty2 <= 0) {
                throw new Exception("Cart empty at checkout.");
            }

            $shippingPerUnit = $ship2 / $qty2;
            $dateCommande = date('Y-m-d');


            $ins = $pdo->prepare("
                INSERT INTO facture
                (code_uti, id_img, date_commande, arr_date, adresse_livraison, frais_livraison)
                VALUES
                (:code_uti, :id_img, :date_commande, :arr_date, :adresse, :frais)
            ");

            foreach ($panier2 as $p) {
                $id_img = (int)$p["id_img"];
                $nb     = (int)$p["nb"];

                for ($i = 0; $i < $nb; $i++) {
                    $ins->execute([
                        ":code_uti" => $code_uti,
                        ":id_img" => $id_img,
                        ":date_commande" => $dateCommande,
                        ":arr_date" => $dateLiv,
                        ":adresse" => $adresse,
                        ":frais" => $shippingPerUnit
                    ]);
                }
            }


            $pdo->prepare("DELETE FROM panier WHERE code_uti = :id")->execute([":id" => $code_uti]);

            $pdo->commit();

            header("Location: order_success.php");
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = "DB error: " . $e->getMessage();
        }
    }


    $panier = load_cart($pdo, $code_uti);
    [$totalQty, $subtotal, $shipping, $total] = compute_totals($pdo, $panier);
}
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

  <?php if ($error): ?>
    <div class="alert"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

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

  <form method="post" class="bottom-row">

    <div class="card">
      <h2 class="card-title">Payment & Summary</h2>

      <div class="notice">
        Mode de paiement simulé dans le cadre du projet (aucun paiement réel).
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

date_arrivee