<?php
require_once "session.php";

if (!isset($_SESSION["user"])) {
    header("Location: log_out.php");
    exit;
}

$user = $_SESSION["user"];
$code_uti = (int)$user->get_id();

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function money_local($v): string {
    return number_format((float)$v, 2, ".", " ") . " €";
}

function compute_status(string $arr_date, ?string $etat_db): array {
    $today = date('Y-m-d');

    
    if ($etat_db === "CANCELLED") {
        return ["Cancelled", "badge cancelled"];
    }

   
    if ($arr_date <= $today) {
        return ["Delivered", "badge delivered"];
    }

   
    if ($etat_db === "PROCESSING") return ["Processing", "badge processing"];
    if ($etat_db === "IN_DELIVERY") return ["In delivery", "badge progress"];

    return ["In delivery", "badge progress"];
}

function can_cancel(string $arr_date, ?string $etat_db): bool {
    $today = date('Y-m-d');
    if ($etat_db === "CANCELLED") return false;
    if ($arr_date <= $today) return false; 
    return true;
}


$flash_ok = null;
$flash_err = null;

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["cancel_order"])) {
    $dc  = $_POST["date_commande"] ?? "";
    $arr = $_POST["arr_date"] ?? "";
    $adr = $_POST["adresse_livraison"] ?? "";
    $token = $_POST["token"] ?? "";

    $expected = hash("sha256", $code_uti . "|" . $dc . "|" . $arr . "|" . $adr);

    if (!hash_equals($expected, $token)) {
        $flash_err = "Invalid request.";
    } elseif (!can_cancel($arr, null)) {
        $flash_err = "This order cannot be cancelled.";
    } else {
        try {
            $pdo->beginTransaction();

         
            $up = $pdo->prepare("
              INSERT INTO commande_etat (code_uti, date_commande, arr_date, adresse_livraison, etat)
              VALUES (:id, :dc, :arr, :adr, 'CANCELLED')
              ON DUPLICATE KEY UPDATE etat = 'CANCELLED'
            ");
            $up->execute([
                ":id" => $code_uti,
                ":dc" => $dc,
                ":arr" => $arr,
                ":adr" => $adr
            ]);

            $pdo->commit();
            header("Location: see_order.php?ok=1");
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $flash_err = "DB error: " . $e->getMessage();
        }
    }
}

if (isset($_GET["ok"])) {
    $flash_ok = "Order cancelled successfully.";
}



$stmt = $pdo->prepare("
  SELECT
    f.code_facture,
    f.id_img,
    f.date_commande,
    f.arr_date,
    f.adresse_livraison,
    f.frais_livraison,
    ce.etat AS etat_commande
  FROM facture f
  LEFT JOIN commande_etat ce
    ON ce.code_uti = f.code_uti
   AND ce.date_commande = f.date_commande
   AND ce.arr_date = f.arr_date
   AND ce.adresse_livraison = f.adresse_livraison
  WHERE f.code_uti = :id
  ORDER BY f.date_commande DESC, f.code_facture DESC
");
$stmt->execute([":id" => $code_uti]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);


$orders = [];

foreach ($rows as $r) {
    $dc  = (string)$r["date_commande"];
    $arr = (string)$r["arr_date"];
    $adr = (string)$r["adresse_livraison"];
    $key = $dc . "||" . $arr . "||" . $adr;

    if (!isset($orders[$key])) {
        $orders[$key] = [
            "date_commande" => $dc,
            "arr_date" => $arr,
            "adresse_livraison" => $adr,
            "etat_commande" => $r["etat_commande"] ?? null,
            "lines" => [],
            "qty" => 0,
            "subtotal" => 0.0,
            "shipping_total" => 0.0,
            "total" => 0.0,
            "images" => []
        ];
    }

    $orders[$key]["lines"][] = $r;
}


foreach ($orders as $k => $o) {
    $subtotal = 0.0;
    $ship = 0.0;
    $qty = 0;
    $imgs = [];

    foreach ($o["lines"] as $line) {
        $imgObj = get_image($pdo, (int)$line["id_img"]);
        if ($imgObj) {
            $subtotal += (float)$imgObj->get_price();
            $imgs[] = $imgObj->get_img();
        }
        $ship += (float)$line["frais_livraison"];
        $qty++;
    }

    $orders[$k]["qty"] = $qty;
    $orders[$k]["subtotal"] = $subtotal;
    $orders[$k]["shipping_total"] = $ship;
    $orders[$k]["total"] = $subtotal + $ship;
    $orders[$k]["images"] = $imgs;
}


$orders = array_values($orders);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Your Orders</title>
  <link rel="stylesheet" href="css/see_order.css">
</head>
<body>

<main class="page">
  <h1 class="title">Your Orders</h1>
  <p class="subtitle">Track your orders, view all items, and cancel when possible.</p>

  <?php if ($flash_ok): ?>
    <div class="alert success"><?= htmlspecialchars($flash_ok) ?></div>
  <?php endif; ?>
  <?php if ($flash_err): ?>
    <div class="alert error"><?= htmlspecialchars($flash_err) ?></div>
  <?php endif; ?>

  <?php if (empty($orders)): ?>
    <div class="empty">
      <p>You have no orders yet.</p>
      <a class="btn secondary" href="index.php">Back to Home</a>
    </div>
  <?php else: ?>

    <?php foreach ($orders as $order): ?>
      <?php
        [$statusText, $statusClass] = compute_status($order["arr_date"], $order["etat_commande"]);
        $canCancel = can_cancel($order["arr_date"], $order["etat_commande"]);

        $token = hash("sha256", $code_uti . "|" . $order["date_commande"] . "|" . $order["arr_date"] . "|" . $order["adresse_livraison"]);

        $images = $order["images"];
        $thumbLimit = 3;
        $thumbs = array_slice($images, 0, $thumbLimit);
        $rest = max(0, count($images) - $thumbLimit);

    
        $domId = "ord_" . substr(hash("sha256", $token), 0, 12);
      ?>

      <section class="order-card" id="<?= htmlspecialchars($domId) ?>">
        <div class="order-left">
          <div class="thumbs">
            <?php foreach ($thumbs as $src): ?>
              <div class="thumb"><img src="<?= $src ?>" alt="Order item"></div>
            <?php endforeach; ?>

            <?php if ($rest > 0): ?>
              <button type="button" class="more" data-toggle="<?= htmlspecialchars($domId) ?>">
                +<?= (int)$rest ?>
              </button>
            <?php endif; ?>
          </div>

          <div class="info">
            <div class="row"><span>Ordered</span><strong><?= htmlspecialchars($order["date_commande"]) ?></strong></div>
            <div class="row"><span>Delivery date</span><strong><?= htmlspecialchars($order["arr_date"]) ?></strong></div>
            <div class="row"><span>Address</span><strong title="<?= htmlspecialchars($order["adresse_livraison"]) ?>"><?= htmlspecialchars($order["adresse_livraison"]) ?></strong></div>
          </div>

          <?php if ($rest > 0): ?>
            <div class="all-images" data-panel="<?= htmlspecialchars($domId) ?>" hidden>
              <div class="all-images-header">
                <strong>All items</strong>
                <button type="button" class="link-btn" data-toggle="<?= htmlspecialchars($domId) ?>">Hide</button>
              </div>

              <div class="all-images-grid">
                <?php foreach ($images as $src): ?>
                  <div class="thumb big"><img src="<?= $src ?>" alt="Order item"></div>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>
        </div>

        <div class="order-right">
          <div class="status-line">
            <span class="<?= $statusClass ?>"><?= htmlspecialchars($statusText) ?></span>
          </div>

          <div class="prices">
            <div class="row"><span>Items</span><strong><?= (int)$order["qty"] ?></strong></div>
            <div class="row"><span>Subtotal</span><strong><?= money_local($order["subtotal"]) ?></strong></div>
            <div class="row"><span>Shipping</span><strong><?= money_local($order["shipping_total"]) ?></strong></div>
            <div class="divider"></div>
            <div class="row total"><span>Total</span><strong><?= money_local($order["total"]) ?></strong></div>
          </div>

          <div class="actions">
            <?php if ($canCancel): ?>
              <form method="post" onsubmit="return confirm('Cancel this order?');">
                <input type="hidden" name="cancel_order" value="1">
                <input type="hidden" name="date_commande" value="<?= htmlspecialchars($order["date_commande"]) ?>">
                <input type="hidden" name="arr_date" value="<?= htmlspecialchars($order["arr_date"]) ?>">
                <input type="hidden" name="adresse_livraison" value="<?= htmlspecialchars($order["adresse_livraison"]) ?>">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                <button class="btn danger" type="submit">Cancel</button>
              </form>
            <?php else: ?>
              <button class="btn disabled" type="button" disabled>Cannot cancel</button>
            <?php endif; ?>

            <a class="btn secondary" href="profile.php">Back to Profile</a>
          </div>
        </div>
      </section>

    <?php endforeach; ?>

  <?php endif; ?>
</main>

<script>

  document.addEventListener("click", function (e) {
    const btn = e.target.closest("[data-toggle]");
    if (!btn) return;

    const id = btn.getAttribute("data-toggle");
    const panel = document.querySelector('[data-panel="' + CSS.escape(id) + '"]');
    if (!panel) return;

    const isHidden = panel.hasAttribute("hidden");
    if (isHidden) panel.removeAttribute("hidden");
    else panel.setAttribute("hidden", "");
  });
</script>

</body>
</html>
