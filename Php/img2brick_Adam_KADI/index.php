<?php
require_once __DIR__ . '/includes/config.php';

// Only for connected users
if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

$ok    = isset($_GET['ok']) || isset($_SESSION['ok']);
$error = isset($_GET['error']) ? trim($_GET['error']) : '';

include __DIR__ . '/includes/header.php';

// Avoiding message stayinn after refeshing page
unset($_SESSION['ok']);
?>

<!-- HERO -->
<section class="py-5 text-center hero">
  <div class="container">
    <h1 class="display-6 fw-bold mb-3"><?= t('title'); ?></h1>
    <p class="lead text-secondary mb-0"><?= t('subtitle'); ?></p>
  </div>
</section>

<!-- MESSAGES -->
<div class="container" aria-live="polite">
  <?php if ($ok): ?>
    <div class="alert alert-success shadow-sm" role="alert">
      <?= t('upload_ok'); ?>
    </div>
  <?php endif; ?>

  <?php if ($error !== ''): ?>
    <div class="alert alert-danger shadow-sm" role="alert">
      <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
    </div>
  <?php endif; ?>
</div>

<!-- MAIN -->
<main class="container pb-5">
  <div class="row justify-content-center">
    <div class="col-12 col-lg-8">
      <div class="card border-0 shadow-lg overflow-hidden">
        <div class="card-body p-4 p-md-5">
          
          <h2 class="h4 fw-bold mb-3"><?= t('upload_title'); ?></h2>
          <p class="text-secondary small mb-4"><?= t('upload_formats'); ?></p>

          <!-- UPLOAD FORM -->
          <form action="preview.php" method="post" enctype="multipart/form-data" novalidate>
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="MAX_FILE_SIZE" value="<?= MAX_UPLOAD_BYTES ?>">

            <div class="upload-wrap mb-4" id="dropzone">
  <label for="file" class="upload-tile mb-0">
    <span class="upload-icon" aria-hidden="true">▢</span>
    <span class="upload-title" id="dropzone-title">
      <?= t('upload_click'); ?>
    </span>
    <span class="upload-sub" id="dropzone-sub">
      <?= t('upload_subtext'); ?><br>
    </span>
  </label>

  <input
    class="visually-hidden"
    id="file"
    name="image"
    type="file"
    accept=".jpg,.jpeg,.png,.webp"
    required
    aria-describedby="fileHelp"
  >
</div>


            

            <div class="d-flex align-items-center gap-3">
              <button type="submit" class="btn btn-primary btn-lg px-4">
                <?= t('button_continue'); ?>
              </button>
            </div>

          </form>
        </div>
      </div>

      <div class="text-center small text-secondary mt-4">
        
      </div>
    </div>
  </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
