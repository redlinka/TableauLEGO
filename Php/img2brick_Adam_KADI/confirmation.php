<?php
require_once __DIR__ . '/includes/config.php';
if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}
include __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/captcha.php';
require_once __DIR__ . '/classes/Email.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

require_csrf_token();

if (!isset($_SESSION['uploaded_image'], $_SESSION['variant'], $_SESSION['board'])) {
    header('Location: index.php?error=Session expired. Please start again.');
    exit;
}

$captchaAnswer = $_POST['captcha_answer'] ?? '';
$honeypot      = $_POST['website'] ?? '';
if (!captcha_verify($captchaAnswer, $honeypot)) {
    header('Location: order.php?error=captcha');
    exit;
}

$firstName = trim($_POST['first_name'] ?? '');
$lastName  = trim($_POST['last_name'] ?? '');
$email     = trim($_POST['email'] ?? '');
$address   = trim($_POST['address'] ?? '');
$zip       = trim($_POST['zip'] ?? '');
$city      = trim($_POST['city'] ?? '');
$country   = trim($_POST['country'] ?? '');
$phone     = trim($_POST['phone'] ?? '');

$orderId = 'CMD-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3)));
$_SESSION['order_id'] = $orderId;

$orderDbId = null;
$_SESSION['last_order_amount'] = null;
$_SESSION['last_order_address'] = [
    'first_name' => $firstName,
    'last_name'  => $lastName,
    'email'      => $email,
    'address'    => $address,
    'zip'        => $zip,
    'city'       => $city,
    'country'    => $country,
    'phone'      => $phone,
];

if (isset($pdo) && $pdo) {
    $userId  = $_SESSION['user_id']  ?? null;
    $imageId = $_SESSION['image_id'] ?? null;
    $board   = $_SESSION['board'];
    $variant = $_SESSION['variant'];

    $price = 99.00;
    if ($board === '32x32') $price = 89.00;
    if ($board === '64x64') $price = 99.00;
    if ($board === '96x96') $price = 129.00;
    $_SESSION['last_order_amount'] = $price;

    $sql = "
        INSERT INTO orders (user_id, image_id, mosaic_id, status, total_amount, validated_at)
        VALUES (:user_id, :image_id, NULL, :status, :total_amount, NOW())
        RETURNING id
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'user_id'      => $userId,
        'image_id'     => $imageId,
        'status'       => 'PAID',
        'total_amount' => $price,
    ]);

    $orderDbId = $stmt->fetchColumn();
    $_SESSION['order_db_id'] = $orderDbId;
}

$emailService = new EmailService();
if ($emailService->isAvailable()) {
    $to = $_SESSION['user_email'] ?? ($_POST['email'] ?? 'test@example.com');
    $subject = 'Your img2brick order ' . $orderId;
    $htmlBody = '
        <h1>' . t('titlec') . '</h1>
        <p>' . t('orderref') . ' <strong>' . htmlspecialchars($orderId, ENT_QUOTES, 'UTF-8') . '</strong></p>
        <p>' . t('var') . ': ' . htmlspecialchars($_SESSION['variant'], ENT_QUOTES, 'UTF-8') . ' — ' . t('size') . ': ' . htmlspecialchars($_SESSION['board'], ENT_QUOTES, 'UTF-8') . '</p>
    ';
    $emailService->send($to, $subject, $htmlBody);
}
?>

<main class="container py-5 text-center">
  <div class="mx-auto" style="max-width:720px">
    <h2 class="display-6 fw-bold"><?= t('titlec'); ?></h2>
    <p class="lead text-secondary">
      <?= t('mosaicc'); ?>
      
      <?php if ($orderDbId): ?>
        <?= t('ordersaved'); ?>
      <?php else: ?>
        <?= t('dbunavailable'); ?>
      <?php endif; ?>
    </p>

    <div class="card shadow-sm my-4">
      <div class="card-body">
        <p class="mb-1">
          <strong><?= t('orderref'); ?></strong>
          <?php echo htmlspecialchars($orderId, ENT_QUOTES, 'UTF-8'); ?>
        </p>

        <?php if ($orderDbId): ?>
          <p class="mb-1">
            <strong><?= t('internalID'); ?></strong>
            <?php echo (int)$orderDbId; ?>
          </p>
        <?php endif; ?>

        <p class="mb-1">
          <strong><?= t('var'); ?></strong>
          <?php echo htmlspecialchars($_SESSION['variant'], ENT_QUOTES, 'UTF-8'); ?>
          &nbsp;·&nbsp;
          <strong><?= t('size'); ?></strong>
          <?php echo htmlspecialchars($_SESSION['board'], ENT_QUOTES, 'UTF-8'); ?>
        </p>

        <img
          src="<?php echo htmlspecialchars($_SESSION['uploaded_image'], ENT_QUOTES, 'UTF-8'); ?>"
          alt="<?= t('choosen_mosaic'); ?>"
          class="img-fluid rounded mt-3"
          style="max-height:320px"
        >

        <div class="mt-3">
          <h3 class="h6 fw-bold mb-2"><?= t('address') ?? 'Address'; ?></h3>
          <p class="mb-1">
            <?php echo htmlspecialchars(trim($firstName . ' ' . $lastName), ENT_QUOTES, 'UTF-8'); ?><br>
            <?php echo htmlspecialchars($address, ENT_QUOTES, 'UTF-8'); ?><br>
            <?php echo htmlspecialchars($zip . ' ' . $city, ENT_QUOTES, 'UTF-8'); ?><br>
            <?php echo htmlspecialchars($country, ENT_QUOTES, 'UTF-8'); ?><br>
            <?php if ($phone): ?>
              <span class="text-muted small"><?php echo htmlspecialchars($phone, ENT_QUOTES, 'UTF-8'); ?></span><br>
            <?php endif; ?>
            <?php if ($email): ?>
              <span class="text-muted small"><?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?></span>
            <?php endif; ?>
          </p>
        </div>

        <?php if (isset($_SESSION['last_order_amount'])): ?>
          <p class="mb-0 mt-2">
            <strong><?= t('amount'); ?></strong>
            <?php echo number_format((float)$_SESSION['last_order_amount'], 2, '.', ' '); ?> €
          </p>
        <?php endif; ?>
      </div>
    </div>

    <a href="index.php" class="btn btn-primary"><?= t('backtohome'); ?></a>
  </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
