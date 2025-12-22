<?php
// User order history
require_once __DIR__ . '/includes/config.php';

if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    header('Location: login.php');
    exit;
}

// Get user's orders
$sql = "
    SELECT 
        o.id,
        o.created_at,
        o.status,
        o.total_amount,
        m.board_size,
        m.variant,
        i.file_path
    FROM orders o
    LEFT JOIN mosaics m ON m.id = o.mosaic_id
    LEFT JOIN images  i ON i.id = o.image_id
    WHERE o.user_id = :uid
    ORDER BY o.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute(['uid' => $userId]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/includes/header.php';
?>

<main class="container py-5">
  <h1 class="mb-3 text-center"><?= t('upload_subtext'); ?></h1>
  <p class="text-center text-muted mb-4">
    <?= t('hereare'); ?>
  </p>

  <?php if (empty($orders)): ?>
    <div class="alert alert-info text-center">
      <?= t('placedorder'); ?>
      <br>
      <a href="index.php" class="btn btn-primary btn-sm mt-2">
        <?= t('start'); ?>
      </a>
    </div>
  <?php else: ?>

    <div class="row g-4">
      <?php foreach ($orders as $order): ?>
        <div class="col-md-6 col-lg-4">
          <div class="card shadow-sm h-100">

            <div class="card-body d-flex flex-column">
              <h2 class="h5 card-title mb-1">
                <?= t('commande'); ?> #<?php echo (int)$order['id']; ?>
              </h2>

              <p class="text-muted small mb-2">
                Placed on
                <?php
                  try {
                      $date = new DateTime($order['created_at']);
                      echo $date->format('Y-m-d H:i');
                  } catch (Exception $e) {
                      echo htmlspecialchars($order['created_at'], ENT_QUOTES, 'UTF-8');
                  }
                ?>
              </p>

              <p class="mb-1">
                <strong><?= t('taille'); ?>:</strong>
                <?php echo htmlspecialchars($order['board_size'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?>
              </p>

              <p class="mb-1">
                <strong>Variant:</strong>
                <?php echo htmlspecialchars($order['variant'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?>
              </p>

              <p class="mb-1">
                <strong>Total:</strong>
                <?php
                  $amount = $order['total_amount'] ?? 0;
                  echo number_format((float)$amount, 2, '.', ' ') . ' €';
                ?>
              </p>

              <p class="mb-2">
                <strong>Status:</strong>
                <span class="badge bg-secondary">
                  <?php echo htmlspecialchars(ucfirst(strtolower($order['status'] ?? 'PENDING')), ENT_QUOTES, 'UTF-8'); ?>
                </span>
              </p>

              <div class="mt-auto pt-2">
                <a class="btn btn-outline-primary btn-sm w-100" href="order_details.php?id=<?php echo (int)$order['id']; ?>">
                  <?= t('details'); ?>
                </a>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

  <?php endif; ?>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
