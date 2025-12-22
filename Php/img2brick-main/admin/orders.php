<?php
// admin/orders.php
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/auth.php';

require_admin(); // access denied if not admin

// Get clients orders
$sql = "
    SELECT
        o.id,
        o.created_at,
        o.status,
        o.total_amount,
        u.email AS user_email
    FROM orders o
    LEFT JOIN users u ON u.id = o.user_id
    ORDER BY o.created_at DESC
";

$stmt = $pdo ? $pdo->query($sql) : null;
$orders = $stmt ? $stmt->fetchAll() : [];

include __DIR__ . '/../includes/header.php';
?>

<main class="container py-5">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 fw-bold mb-0"><?= t('backoffice'); ?></h1>
    <a href="/index.php" class="btn btn-outline-secondary btn-sm"><?= t('getback'); ?></a>
  </div>

  <?php if (!$pdo): ?>
    <div class="alert alert-danger">
      <?= t('dberrcon'); ?>
    </div>
  <?php else: ?>

    <?php if (!$orders): ?>
      <p><?= t('noorders'); ?></p>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover align-middle">
          <thead class="table-light">
            <tr>
              <th>ID</th>
              <th>Date</th>
              <th>Client</th>
              <th><?= t('status'); ?></th>
              <th><?= t('amount'); ?></th>
              <th></th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($orders as $order): ?>
            <tr>
              <td>#<?php echo htmlspecialchars($order['id'], ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars($order['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars($order['user_email'] ?? 'Invite', ENT_QUOTES, 'UTF-8'); ?></td>
              <td>
                <?php
                  $status = $order['status'];
                  $badgeClass = 'secondary';
                  if ($status === 'CART')      $badgeClass = 'warning';
                  if ($status === 'PENDING')   $badgeClass = 'info';
                  if ($status === 'PAID')      $badgeClass = 'success';
                  if ($status === 'CANCELLED') $badgeClass = 'danger';
                ?>
                <span class="badge bg-<?php echo $badgeClass; ?>">
                  <?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>
                </span>
              </td>
              <td>
                <?php echo number_format((float)$order['total_amount'], 2, ',', ' '); ?> €
              </td>
              <td class="text-end">
                <a href="#" class="btn btn-sm btn-outline-primary disabled" aria-disabled="true">
                  <?= t('details'); ?>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

  <?php endif; ?>
</main>
//NOT SURE IF I WILL PUT IT IN THE FINAL PROJECT
<?php include __DIR__ . '/../includes/footer.php'; ?>