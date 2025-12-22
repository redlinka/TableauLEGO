<?php
require_once __DIR__ . '/includes/config.php';

// Crop/preview step (page 2).
if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

require_csrf_token();

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    header('Location: index.php?error=' . urlencode(t('uploadfailed')));
    exit;
}

$file = $_FILES['image'];

// Size max verification
if ($file['size'] > MAX_UPLOAD_BYTES) {
    header('Location: index.php?error=' . urlencode(t('filetoolarge')));
    exit;
}

// Image verification
$imgInfo = @getimagesize($file['tmp_name']);
if ($imgInfo === false) {
    header('Location: index.php?error=' . urlencode(t('invalidimage')));
    exit;
}

[$w, $h] = $imgInfo;
$mime = $imgInfo['mime'] ?? '';

// Dimensions verif size min
if ($w < MIN_DIM || $h < MIN_DIM) {
    header('Location: index.php?error=' . urlencode(t('imagetoosmall')));
    exit;
}

// Enforce safe extensions + MIME to avoid PHP/webshell uploads.
$allowedExt  = ['jpg', 'jpeg', 'png', 'webp'];
$allowedMime = ['image/jpeg', 'image/png', 'image/webp'];
$ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$finfo = new finfo(FILEINFO_MIME_TYPE);
$detectedMime = $finfo ? $finfo->file($file['tmp_name']) : $mime;
if (!in_array($ext, $allowedExt, true) || !in_array($detectedMime, $allowedMime, true)) {
    header('Location: index.php?error=' . urlencode(t('invalidimage')));
    exit;
}

// Upload folder
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

// Unique file name 
$name = 'img_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$fullPath = UPLOAD_DIR . '/' . $name;

// File move
if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
    header('Location: index.php?error=' . urlencode(t('movefailed')));
    exit;
}

@chmod($fullPath, 0644); // remove execute bits on uploads

// Web path
$imagePath = 'uploads/' . $name;

// Session save for displaying
$_SESSION['uploaded_image'] = $imagePath;

// Database saving
$imageId = null;

if (isset($pdo) && $pdo) {
    $userId = $_SESSION['user_id'] ?? null; 

    $stmt = $pdo->prepare("
        INSERT INTO images (user_id, file_path, width, height, mime_type)
        VALUES (:user_id, :file_path, :width, :height, :mime_type)
        RETURNING id
    ");

    $stmt->execute([
        'user_id'   => $userId,
        'file_path' => $imagePath,
        'width'     => $w,
        'height'    => $h,
        'mime_type' => $mime,
    ]);

    $imageId = $stmt->fetchColumn();
    $_SESSION['image_id'] = $imageId;
}

include __DIR__ . '/includes/header.php';
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.css">

<main class="container py-5">
  <div class="row justify-content-center">
    <div class="col-12 col-lg-10">
      <div class="card shadow-lg border-0">
        <div class="card-body p-4 p-md-5">
          <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-3">
            <div>
              <h1 class="h4 fw-bold mb-2">Preview</h1>
              <p class="text-secondary mb-0 small">
                Adjust your image (square or rectangle) before generating your mosaics.
              </p>
            </div>
            <div class="btn-group" role="group" aria-label="Aspect ratio">
              <button type="button" class="btn btn-outline-secondary active" data-aspect="1">Square</button>
              <button type="button" class="btn btn-outline-secondary" data-aspect="1.3333">Rectangle</button>
            </div>
          </div>

          <div class="cropper-area mb-4">
            <img
              id="cropperImage"
              src="<?php echo htmlspecialchars($imagePath, ENT_QUOTES, 'UTF-8'); ?>"
              alt="Crop your image"
              class="w-100"
              style="max-height:620px;"
            >
          </div>

          <form id="cropForm" action="results.php" method="post" class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="cropped_data" id="cropped_data">
            <input type="hidden" name="crop_aspect" id="crop_aspect" value="1">
            <div class="text-secondary small">
              Your crop will be applied to all mock previews.
            </div>
            <div class="d-flex gap-2">
              <a class="btn btn-outline-secondary" href="index.php"><?= t('return'); ?></a>
              <button type="submit" class="btn btn-primary px-4" id="generateBtn"><?= t('button_continue') ?? 'Generate my mosaic'; ?></button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const cropImg = document.getElementById('cropperImage');
  const aspectButtons = document.querySelectorAll('[data-aspect]');
  const croppedField = document.getElementById('cropped_data');
  const aspectField = document.getElementById('crop_aspect');
  const form = document.getElementById('cropForm');
  if (!cropImg || !window.Cropper) return;

  let currentAspect = 1;
  const cropper = new Cropper(cropImg, {
    aspectRatio: currentAspect,
    viewMode: 2,
    background: false,
    autoCropArea: 1,
    responsive: true
  });

  function setAspect(aspect) {
    currentAspect = aspect;
    cropper.setAspectRatio(aspect);
    if (aspectField) aspectField.value = aspect;
    aspectButtons.forEach(btn => {
      const isActive = parseFloat(btn.dataset.aspect || '1') === aspect;
      btn.classList.toggle('active', isActive);
      btn.classList.toggle('btn-primary', isActive);
      btn.classList.toggle('btn-outline-secondary', !isActive);
    });
  }

  aspectButtons.forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      const aspect = parseFloat(btn.dataset.aspect || '1');
      setAspect(aspect || 1);
    });
  });

  function captureCrop() {
    const canvas = cropper.getCroppedCanvas({
      width: currentAspect === 1 ? 800 : 960,
      height: currentAspect === 1 ? 800 : Math.round(960 / currentAspect)
    });
    if (!canvas || !croppedField) return false;
    // To keep size reasonable, compress to JPEG
    const dataUrl = canvas.toDataURL('image/jpeg', 0.85);
    croppedField.value = dataUrl;
    return true;
  }

  form.addEventListener('submit', (e) => {
    if (!captureCrop()) {
      e.preventDefault();
      alert('Crop could not be captured. Please retry.');
    }
  });

  // init
  setAspect(currentAspect);
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
