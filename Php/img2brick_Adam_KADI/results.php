<?php
require_once __DIR__ . '/includes/config.php';

// Protected page: needs a session + uploaded image

if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();
}

if (!isset($_SESSION['uploaded_image'])) {
    header('Location: index.php?error=' . urlencode(t('uploadfirst')));
    exit;
}

$imagePath = $_SESSION['uploaded_image'];
$imageId   = $_SESSION['image_id'] ?? null;

// If coming from crop step, save the cropped image (base64) and update session/DB.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cropped_data'])) {
    $dataUrl = (string)($_POST['cropped_data'] ?? '');
    if (strpos($dataUrl, 'data:image') === 0) {
        $parts = explode(',', $dataUrl, 2);
        if (count($parts) === 2) {
            $decoded = base64_decode($parts[1], true);
            if ($decoded !== false && strlen($decoded) <= 8 * 1024 * 1024) { // 8MB safety limit
                $newName = 'crop_' . time() . '_' . bin2hex(random_bytes(4)) . '.jpg';
                $newPath = UPLOAD_DIR . '/' . $newName;
                if (file_put_contents($newPath, $decoded) !== false) {
                    @chmod($newPath, 0644);
                    $imgInfo = @getimagesize($newPath);
                    if ($imgInfo !== false && ($imgInfo[0] >= MIN_DIM && $imgInfo[1] >= MIN_DIM)) {
                        $webPath = 'uploads/' . $newName;
                        $_SESSION['uploaded_image'] = $webPath;
                        $imagePath = $webPath;
                        // Update DB record with cropped version
                        if ($imageId && isset($pdo) && $pdo) {
                            $upd = $pdo->prepare("UPDATE images SET file_path = :p, width = :w, height = :h, mime_type = :m WHERE id = :id");
                            $upd->execute([
                                'p' => $webPath,
                                'w' => (int)$imgInfo[0],
                                'h' => (int)$imgInfo[1],
                                'm' => $imgInfo['mime'] ?? 'image/jpeg',
                                'id'=> (int)$imageId,
                            ]);
                        }
                    }
                }
            }
        }
    }
}

// Board size : POST > session > default
$board = $_POST['board_size'] ?? ($_SESSION['board_size'] ?? '64x64');
$_SESSION['board_size'] = $board;

// Variant : POST > session > default
$variant = $_POST['variant'] ?? ($_SESSION['variant'] ?? 'blue');
$_SESSION['variant'] = $variant;

// Pricing mock (same as confirmation)
$price = 99.00;
if ($board === '32x32') $price = 89.00;
if ($board === '64x64') $price = 99.00;
if ($board === '96x96') $price = 129.00;

$colorsByVariant = [
    'blue' => 48,
    'red'  => 52,
    'bw'   => 24,
];

include __DIR__ . '/includes/header.php';
?>

<?php if (!empty($LOAD_CROPPER)): ?>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.css">
<?php endif; ?>

<main class="container py-5">
  <div class="mb-4 text-center">
    <h2 class="fw-bold"><?= t('results_title'); ?></h2>
    <p class="text-secondary mb-0"><?= t('results_subtitle'); ?></p>
  </div>

  <form action="order.php" method="post" class="d-flex flex-column gap-4">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">

    <!-- Board size -->
    <div class="d-flex justify-content-center mb-3">
      <div class="card shadow-sm p-3" style="max-width: 520px;">
        <h3 class="h5 mb-2 text-center"><?= t('results_board_title'); ?></h3>
        <p class="small text-muted text-center mb-3">
          <?= t('results_board_subtitle'); ?>
        </p>

        <div class="form-check mb-1">
          <input
            class="form-check-input"
            type="radio"
            name="board_size"
            id="board32"
            value="32x32"
            <?php echo $board === '32x32' ? 'checked' : ''; ?>
          >
          <label class="form-check-label" for="board32">
            <?= t('results_board_32'); ?>
          </label>
        </div>

        <div class="form-check mb-1">
          <input
            class="form-check-input"
            type="radio"
            name="board_size"
            id="board64"
            value="64x64"
            <?php echo $board === '64x64' ? 'checked' : ''; ?>
          >
          <label class="form-check-label" for="board64">
            <?= t('results_board_64'); ?>
          </label>
        </div>

        <div class="form-check">
          <input
            class="form-check-input"
            type="radio"
            name="board_size"
            id="board96"
            value="96x96"
            <?php echo $board === '96x96' ? 'checked' : ''; ?>
          >
          <label class="form-check-label" for="board96">
            <?= t('results_board_96'); ?>
          </label>
        </div>
      </div>
    </div>

    <!-- Variant choice -->
    <div class="row g-4">

      <div class="col-12 col-md-4">
        <label class="card shadow-sm h-100 selectable-card">
          <div class="pixel-wrap">
          <img
            src="<?php echo htmlspecialchars($imagePath, ENT_QUOTES, 'UTF-8'); ?>"
            data-src="<?php echo htmlspecialchars($imagePath, ENT_QUOTES, 'UTF-8'); ?>"
            alt="<?= t('blueaccent'); ?>"
            class="mosaic-preview mosaic-blue"
          >
          </div>
          <div class="card-body">
            <div class="form-check">
              <input
                class="form-check-input"
                type="radio"
                name="variant"
                id="variant_blue"
                value="blue"
                <?php echo $variant === 'blue' ? 'checked' : ''; ?>
                required
              >
              <label class="form-check-label fw-semibold" for="variant_blue">
                <?= t('blueaccent2'); ?>
              </label>
            </div>
            <p class="small text-secondary mb-0 mt-2">
              <?= t('results_meta_blue'); ?>
              <br>
              
              <strong><?= t('amount'); ?>:</strong> <?php echo number_format($price, 2, '.', ' '); ?> €
              • <strong><?= t('color') ?? 'Colors'; ?>:</strong> <?php echo $colorsByVariant['blue']; ?>
            </p>
          </div>
        </label>
      </div>

      <div class="col-12 col-md-4">
        <label class="card shadow-sm h-100 selectable-card">
          <div class="pixel-wrap">
          <img
            src="<?php echo htmlspecialchars($imagePath, ENT_QUOTES, 'UTF-8'); ?>"
            data-src="<?php echo htmlspecialchars($imagePath, ENT_QUOTES, 'UTF-8'); ?>"
            alt="<?= t('redaccent'); ?>"
            class="mosaic-preview mosaic-red"
          >
          </div>
          <div class="card-body">
            <div class="form-check">
              <input
                class="form-check-input"
                type="radio"
                name="variant"
                id="variant_red"
                value="red"
                <?php echo $variant === 'red' ? 'checked' : ''; ?>
                required
              >
              <label class="form-check-label fw-semibold" for="variant_red">
                <?= t('redaccent'); ?>
              </label>
            </div>
            <p class="small text-secondary mb-0 mt-2">
              <?= t('results_meta_red'); ?>
              <br>
             
              <strong><?= t('amount'); ?>:</strong> <?php echo number_format($price, 2, '.', ' '); ?> €
              • <strong><?= t('color') ?? 'Colors'; ?>:</strong> <?php echo $colorsByVariant['red']; ?>
            </p>
          </div>
        </label>
      </div>

      <div class="col-12 col-md-4">
        <label class="card shadow-sm h-100 selectable-card">
         <div class="pixel-wrap">
          <img
            src="<?php echo htmlspecialchars($imagePath, ENT_QUOTES, 'UTF-8'); ?>"
            data-src="<?php echo htmlspecialchars($imagePath, ENT_QUOTES, 'UTF-8'); ?>"
            alt="<?= t('bwaccent'); ?>"
            class="mosaic-preview mosaic-bw"
          >
          </div>
          <div class="card-body">
            <div class="form-check">
              <input
                class="form-check-input"
                type="radio"
                name="variant"
                id="variant_bw"
                value="bw"
                <?php echo $variant === 'bw' ? 'checked' : ''; ?>
                required
              >
              <label class="form-check-label fw-semibold" for="variant_bw">
                <?= t('bwaccent2'); ?>
              </label>
            </div>
            <p class="small text-secondary mb-0 mt-2">
              <?= t('results_meta_bw'); ?>
              <br>
              
              <strong><?= t('amount'); ?>:</strong> <?php echo number_format($price, 2, '.', ' '); ?> €
              • <strong><?= t('color') ?? 'Colors'; ?>:</strong> <?php echo $colorsByVariant['bw']; ?>
            </p>
          </div>
        </label>
      </div>

    </div>

    <div class="d-flex justify-content-between mt-4">
      <a href="index.php" class="btn btn-outline-secondary"><?= t('return'); ?></a>
      <button type="submit" class="btn btn-primary px-4"><?= t('validatechoice'); ?></button>
    </div>

    <!-- Compatibility with order.php -->
    <input type="hidden" name="board" id="boardHidden" value="<?php echo htmlspecialchars($board, ENT_QUOTES, 'UTF-8'); ?>">
  </form>
</main>

<script>
document.addEventListener('DOMContentLoaded', () => {
  // Mirror board radio into hidden input (keeps compat with order.php)
  const hiddenBoard = document.getElementById('boardHidden');
  document.querySelectorAll('input[name="board_size"]').forEach(r => {
    r.addEventListener('change', () => {
      if (hiddenBoard) hiddenBoard.value = r.value;
    });
  });

  const previews = Array.from(document.querySelectorAll('.mosaic-preview'));
  const getBoardPixels = () => {
    const checked = document.querySelector('input[name="board_size"]:checked');
    const value = checked ? checked.value : '64x64';
    const parsed = parseInt(value.split('x')[0], 10);
    return Number.isFinite(parsed) && parsed > 0 ? parsed : 64;
  };

  const renderPixelated = (img, size) => {
    const src = img.dataset.src || img.getAttribute('data-src') || img.src;
    if (!src) return;
    const baseSize = Number.isFinite(size) ? size : 64;
    const original = new Image();
    original.decoding = 'async';
    original.onload = () => {
      const maxSide = Math.max(original.naturalWidth, original.naturalHeight) || 1;
      const scale = baseSize / maxSide;
      const targetW = Math.max(1, Math.round(original.naturalWidth * scale));
      const targetH = Math.max(1, Math.round(original.naturalHeight * scale));
      const canvas = document.createElement('canvas');
      canvas.width = targetW;
      canvas.height = targetH;
      const ctx = canvas.getContext('2d');
      if (!ctx) return;
      ctx.imageSmoothingEnabled = false;
      ctx.drawImage(original, 0, 0, targetW, targetH);
      img.src = canvas.toDataURL('image/jpeg', 0.92);
    };
    original.src = src;
  };

  const refreshPreviews = () => {
    const size = getBoardPixels();
    previews.forEach(img => renderPixelated(img, size));
  };

  document.querySelectorAll('input[name="board_size"]').forEach(r => {
    r.addEventListener('change', refreshPreviews);
  });

  refreshPreviews();
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
