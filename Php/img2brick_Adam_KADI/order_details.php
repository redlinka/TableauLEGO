<?php
require_once __DIR__ . '/includes/config.php';

if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

$orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($orderId <= 0) {
    header('Location: commandes.php');
    exit;
}

$userId = $_SESSION['user_id'] ?? 0;

$sql = "
    SELECT 
        o.id,
        o.created_at,
        o.status,
        o.total_amount,
        o.validated_at,
        m.board_size,
        m.variant,
        i.file_path
    FROM orders o
    LEFT JOIN mosaics m ON m.id = o.mosaic_id
    LEFT JOIN images  i ON i.id = o.image_id
    WHERE o.id = :id AND o.user_id = :uid
    LIMIT 1
";

$stmt = $pdo->prepare($sql);
$stmt->execute(['id' => $orderId, 'uid' => $userId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header('Location: commandes.php');
    exit;
}

include __DIR__ . '/includes/header.php';
?>

<main class="container py-5">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 fw-bold mb-0"><?= t('details'); ?> #<?php echo (int)$order['id']; ?></h1>
    <a class="btn btn-outline-secondary btn-sm" href="commandes.php"><?= t('return'); ?></a>
  </div>

  <div class="row g-4">
    <div class="col-12 col-lg-7">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <h2 class="h5 mb-3"><?= t('commande'); ?> #<?php echo (int)$order['id']; ?></h2>
          <p class="mb-1"><strong>Date:</strong> <?php echo htmlspecialchars($order['created_at'], ENT_QUOTES, 'UTF-8'); ?></p>
          <p class="mb-1"><strong><?= t('status'); ?>:</strong> <?php echo htmlspecialchars($order['status'], ENT_QUOTES, 'UTF-8'); ?></p>
          <p class="mb-1"><strong><?= t('amount'); ?>:</strong> <?php echo number_format((float)$order['total_amount'], 2, '.', ' '); ?> €</p>
          <p class="mb-1"><strong><?= t('size'); ?>:</strong> <?php echo htmlspecialchars($order['board_size'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></p>
          <p class="mb-1"><strong>Variant:</strong> <?php echo htmlspecialchars($order['variant'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
      </div>
    </div>
    <div class="col-12 col-lg-5">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <h3 class="h6 fw-bold mb-3"><?= t('choosen_mosaic'); ?></h3>
          <?php if (!empty($order['file_path'])): ?>
            <img src="<?php echo htmlspecialchars($order['file_path'], ENT_QUOTES, 'UTF-8'); ?>" alt="Order image" class="img-fluid rounded">
          <?php else: ?>
            <p class="text-secondary small">No image available.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
