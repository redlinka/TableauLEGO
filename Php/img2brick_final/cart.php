<?php
session_start();
global $cnx;
include("./config/cnx.php");
require_once __DIR__ . '/includes/i18n.php';

if (!isset($_SESSION['userId'])) {
  header("Location: connexion.php");
  exit;
}

$errors = [];
$id_uti = (int)$_SESSION['userId'];
$imgFolder = 'users/imgs/';
$tilingFolder = 'users/tilings/';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_pavage_id'])) {
  $pavageId = (int)$_POST['remove_pavage_id'];
  $userId   = (int)($_SESSION['userId'] ?? 0);

  try {
    $cnx->beginTransaction();

    // Retrieve file name
    $stmt = $cnx->prepare("SELECT pavage_txt, image_id FROM TILLING WHERE pavage_id = ?");
    $stmt->execute([$pavageId]);
    $tiling = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($tiling) {
      // Delete from contain
      $delContain = $cnx->prepare("DELETE FROM contain WHERE pavage_id = ?")->execute([$pavageId]);

      // Delete from TILLING
      $delTilling = $cnx->prepare("DELETE FROM TILLING WHERE pavage_id = ?")->execute([$pavageId]);

      // Delete tilling from disk
      $txtPath = __DIR__ . $tilingFolder . $tiling['pavage_txt'];
      if (file_exists($txtPath)) {
        unlink($txtPath);
      }
      $rootImageId = (int)$tiling['image_id'];
      while (true) {
        $stmt = $cnx->prepare("SELECT img_parent FROM IMAGE WHERE image_id = ?");
        $stmt->execute([$rootImageId]);
        $parentId = $stmt->fetchColumn();

        if (!$parentId) {
          break; // root found
        }
        $rootImageId = (int)$parentId;
      }
      // Delete all images under root
      $imgDirPath = __DIR__ . '/users/imgs';
      $tilingDirPath = __DIR__ . '/users/tilings';

      deleteDescendants($cnx, $rootImageId, $imgDirPath, $tilingDirPath, false);
    }
    $cnx->commit();
    addLog($cnx, "USER", "DELETE", "pavage");
    header("Location: cart.php");
    exit;
  } catch (PDOException $e) {
    if ($cnx->inTransaction()) $cnx->rollBack();
    header("Location: cart.php?error=delete_failed"); // create the error message
    exit;
  }
}

function money($v)
{
  return number_format((float)$v, 2, ".", " ") . " EUR";
}

$stmt = $cnx->prepare("
    SELECT 
        o.order_id,
        c.pavage_id,
        i.path AS lego_path,
        t.pavage_txt
    FROM ORDER_BILL o
    JOIN contain c ON c.order_id = o.order_id
    JOIN TILLING t ON t.pavage_id = c.pavage_id
    JOIN IMAGE i ON i.image_id = t.image_id
    WHERE o.user_id = :user_id
      AND o.created_at IS NULL
");
$stmt->execute(['user_id' => $id_uti]);
$row_pan = $stmt->fetchAll(PDO::FETCH_ASSOC);

$items = [];
$subtotal = 0.0;

foreach ($row_pan as $row) {

  $id_pavage = (int)($row["pavage_id"] ?? 0);

  $legoPath = (string)($row['lego_path'] ?? '');
  $src = $imgFolder . ltrim($legoPath, '/');

  $price = 0.0;
  $pavageFile = trim((string)($row['pavage_txt'] ?? ''));
  $txtPath = __DIR__ . "/users/tilings/" . $pavageFile;

  if ($pavageFile !== '' && is_file($txtPath) && is_readable($txtPath)) {
    $txtContent = file_get_contents($txtPath);
    if ($txtContent !== false && preg_match('/\d+/', $txtContent, $m)) {
      $price = ((float)$m[0]) / 100;
    }
  }

  $subtotal += $price;
  $line_total = $price;

  $items[] = [
    "id_pavage"   => $id_pavage,
    "src"         => $src,
    "price"       => $price,
    "line_total"  => $line_total,
  ];
}

$shipping = $subtotal * 0.10;
$total = $subtotal + $shipping;
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars(tr('cart.page_title', 'My Cart')) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      font-family: Arial, sans-serif;
      background: #f6f6f6;
      color: #222;
    }

    .cart-page {
      max-width: 1200px;
      margin: 0 auto;
      padding: 30px 16px 60px;
    }

    .title {
      text-align: center;
      margin-bottom: 20px;
      font-size: 34px;
      font-weight: 800;
    }

    .cart-layout {
      display: grid;
      grid-template-columns: 1fr 360px;
      gap: 18px;
    }

    .cart-left,
    .cart-right {
      background: #fff;
      border: 1px solid #e6e6e6;
      border-radius: 14px;
      padding: 16px;
      box-shadow: 0 8px 20px rgba(0, 0, 0, 0.06);
    }

    .section-title {
      margin: 0 0 12px;
      font-size: 20px;
    }

    .empty {
      color: #666;
    }

    .items-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 14px;
    }

    .item-card {
      border: 1px solid #eee;
      border-radius: 14px;
      overflow: hidden;
      background: #fafafa;
    }

    .thumb {
      height: 170px;
      background: #eee;
    }

    .thumb img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
    }

    .meta {
      padding: 10px 12px;
    }

    .meta-row {
      display: flex;
      justify-content: space-between;
      margin-top: 6px;
    }

    .summary-box {
      display: grid;
      gap: 10px;
    }

    .sum-row {
      display: flex;
      justify-content: space-between;
    }

    .sum-divider {
      height: 1px;
      background: #e6e6e6;
    }

    .sum-row.total {
      font-size: 18px;
    }

    .order-btn {
      width: 100%;
      padding: 12px;
      border-radius: 12px;
      border: 0;
      background: #222;
      color: #fff;
      font-weight: 800;
      cursor: pointer;
    }

    .order-btn:disabled {
      opacity: .45;
      cursor: not-allowed;
    }

    .back-link {
      display: block;
      text-align: center;
      margin-top: 10px;
      padding: 10px;
      border-radius: 12px;
      border: 1px solid #ddd;
      text-decoration: none;
      color: #222;
    }

    @media (max-width: 980px) {
      .cart-layout {
        grid-template-columns: 1fr;
      }

      .items-grid {
        grid-template-columns: 1fr;
      }
    }

    .remove-form {
      margin-top: 10px;
      text-align: right;
    }

    .remove-btn {
      background: #e74c3c;
      color: #fff;
      border: 0;
      padding: 6px 12px;
      border-radius: 8px;
      font-size: 13px;
      font-weight: 700;
      cursor: pointer;
    }

    .remove-btn:hover {
      background: #c0392b;
    }
  </style>
</head>

<body>
  <?php include("./includes/navbar.php"); ?>
  <main class="cart-page">
    <br>
    <h1 class="title" data-i18n="cart.title">My Cart</h1>

    <div class="cart-layout">
      <section class="cart-left">
        <h2 class="section-title" data-i18n="cart.items">Items</h2>

        <?php if (empty($items)): ?>
          <p class="empty" data-i18n="cart.empty">Your cart is empty.</p>
        <?php else: ?>
          <div class="items-grid">
            <?php foreach ($items as $it): ?>
              <article class="item-card">
                <div class="thumb">
                  <img src="<?= htmlspecialchars($it['src'] ?: '/images/placeholder.png') ?>" alt="Item" data-i18n-attr="alt:cart.item_alt">
                </div>

                <div class="meta">
                  <form method="post" action="cart.php" class="remove-form">
                    <input type="hidden" name="remove_pavage_id" value="<?= (int)$it['id_pavage'] ?>">
                    <button type="submit" class="remove-btn" data-i18n="cart.remove">Remove</button>
                  </form>

                  <div class="meta-row">
                    <span data-i18n="cart.unit">Unit</span>
                    <strong><?= money($it["price"]) ?></strong>
                  </div>

                  <div class="meta-row">
                    <span data-i18n="cart.line">Line</span>
                    <strong><?= money($it["line_total"]) ?></strong>
                  </div>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>

      <aside class="cart-right">
        <h2 class="section-title" data-i18n="cart.summary">Summary</h2>

        <div class="summary-box">
          <div class="sum-row">
            <span data-i18n="cart.subtotal">Subtotal</span>
            <strong><?= money($subtotal) ?></strong>
          </div>

          <div class="sum-row">
            <span data-i18n="cart.shipping">Shipping (10%)</span>
            <strong><?= money($shipping) ?></strong>
          </div>

          <div class="sum-divider"></div>

          <div class="sum-row total">
            <span data-i18n="cart.total">Total</span>
            <strong><?= money($total) ?></strong>
          </div>

          <?php if (!empty($items)): ?>
            <a href="order.php" class="order-btn" style="text-decoration: none; display: block; text-align: center;" data-i18n="cart.order">
              Order
            </a>
          <?php else: ?>
            <button class="order-btn" disabled data-i18n="cart.order">Order</button>
          <?php endif; ?>

          <a href="index.php" class="back-link" data-i18n="cart.back">Back to start</a>
        </div>
      </aside>
    </div>
  </main>

  <script src="assets/i18n.js"></script>
</body>

</html>