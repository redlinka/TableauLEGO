<?php
require_once __DIR__ . '/config/session.php';
global $cnx;
include("./config/cnx.php");
require_once __DIR__ . '/includes/i18n.php';

// Redirect to home if no previous image step exists
if (!isset($_SESSION['step0_image_id'])) {
    header("Location: index.php");
    exit;
}

$parentId = $_SESSION['step0_image_id'];
$_SESSION['redirect_after_login'] = 'crop_selection.php';
$imgDir = 'users/imgs/';
$tilingDir = 'users/tilings/';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!csrf_validate($_POST['csrf'] ?? null)) {
        $errors[] = 'Invalid session (CSRF). Please refresh and try again.';
    }

    if (empty($errors)) {
        if (empty($_POST['image'])) {
            $errors[] = 'No image data received.';
        } else {
            // Validate dimensions
            $width = (int)($_POST['width'] ?? 0);
            $height = (int)($_POST['height'] ?? 0);

            if ($width < 16 || $height < 16) {
                $errors[] = "Dimensions must be at least 16x16.";
            }
            if ($width % 16 !== 0 || $height % 16 !== 0) {
                $errors[] = "Dimensions must be multiples of 16.";
            }
            if ($width > 512 || $height > 512) {
                $errors[] = "Maximum dimension is 512 studs.";
            }

            if (empty($errors)) {
                // Decode the Base64 image sent by the form
                $imageParts = explode(";base64,", $_POST['image']);

                if (count($imageParts) < 2) {
                    $errors[] = "Invalid image format.";
                } else {
                    $imageType = explode("image/", $imageParts[0])[1];
                    $imageBase64 = base64_decode($imageParts[1]);

                    $existingId = $_SESSION['step1_image_id'] ?? null;
                    $isUpdate = false;
                    $targetFilename = null;

                    // Check if we are updating (Going back/forward)
                    if ($existingId) {
                        $stmt = $cnx->prepare("SELECT path FROM IMAGE WHERE image_id = ? AND img_parent = ?");
                        $stmt->execute([$existingId, $parentId]);
                        $existingRow = $stmt->fetch();

                        if ($existingRow) {
                            $isUpdate = true;
                            $targetFilename = $existingRow['path'];
                        }
                    }
                    // If no existing file to update, generate a new random name
                    if (!$targetFilename) {
                        $targetFilename = bin2hex(random_bytes(16)) . '.' . $imageType;
                    }
                    $targetPath = $imgDir . $targetFilename;

                    // Save to Disk
                    if (file_put_contents(__DIR__ . '/' . $targetPath, $imageBase64)) {
                        try {
                            if ($isUpdate) {
                                $stmt = $cnx->prepare("UPDATE IMAGE SET filename = 'Cropped Image', created_at = NOW() WHERE image_id = ?");
                                $stmt->execute([$existingId]);

                                // Clean up forward history since crop changed
                                deleteDescendants($cnx, $existingId, $imgDir, $tilingDir, true);
                                unset($_SESSION['step2_image_id']);
                                unset($_SESSION['step3_image_id']);
                                unset($_SESSION['step4_image_id']);
                            } else {
                                $stmt = $cnx->prepare("INSERT INTO IMAGE (user_id, filename, path, created_at, img_parent) VALUES (:user_id, 'Cropped Image', :path, NOW(), :img_parent)");
                                $userId = $_SESSION['userId'] ?? NULL;

                                $stmt->execute([
                                    ':user_id'    => $userId,
                                    ':path'       => $targetFilename,
                                    ':img_parent' => $parentId
                                ]);

                                $_SESSION['step1_image_id'] = $cnx->lastInsertId();
                            }

                            // Save dimensions to session
                            $_SESSION['target_width'] = $width;
                            $_SESSION['target_height'] = $height;

                            addLog($cnx, "USER", "CROP", "image");
                            addLog($cnx, "USER", "CHOOSE", "dimensions");

                            // Skip dimensions_selection, go directly to downscale
                            header("Location: downscale_selection.php");
                            exit;
                        } catch (PDOException $e) {
                            if (!$isUpdate && file_exists(__DIR__ . '/' . $targetPath)) {
                                unlink(__DIR__ . '/' . $targetPath);
                            }
                            http_response_code(500);
                            $errors[] = "Database error: " . $e->getMessage();
                        }
                    } else {
                        http_response_code(500);
                        $errors[] = "Server error: could not save cropped image.";
                    }
                }
            }
        }
    }
}

// Get request: fetch and display image
try {
    $stmt = $cnx->prepare("SELECT path, width, height FROM IMAGE WHERE image_id = ?");
    $stmt->execute([$parentId]);
    $image = $stmt->fetch();

    if (!$image) {
        http_response_code(404);
        die("Image not found");
    }
    $displayPath = $imgDir . $image['path'] . '?t=' . time();
    $origW = (int)$image['width'];
    $origH = (int)$image['height'];
} catch (PDOException $e) {
    http_response_code(500);
    die("Database Error");
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(tr('crop.page_title', 'Step 1: Crop & Size')) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .crop-area {
            height: 60vh;
            background: #212529;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            overflow: hidden;
        }

        .crop-area img {
            max-width: 100%;
            max-height: 100%;
            display: block;
        }

        .error-list {
            background-color: #fee;
            border: 1px solid #fcc;
            border-radius: 4px;
            padding: 10px;
            margin-bottom: 15px;
        }
        .error-list ul { margin: 5px 0; padding-left: 20px; }
        .error-list li { color: #c00; }

        .preset-btn {
            text-align: left;
            position: relative;
            margin-bottom: 6px;
            transition: all 0.2s;
        }
        .preset-btn small {
            display: block;
            font-size: 0.75rem;
            opacity: 0.8;
        }
        .preset-btn.active {
            background-color: #6c757d;
            color: white;
            border-color: #6c757d;
        }

        .size-info {
            background: #e8f4fd;
            border: 1px solid #bee5eb;
            border-radius: 8px;
            padding: 12px;
            text-align: center;
            font-weight: 600;
        }

        .step-indicator {
            display: flex;
            gap: 8px;
            margin-bottom: 16px;
        }
        .step-dot {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: bold;
            color: white;
        }
        .step-dot.active { background-color: #0d6efd; }
        .step-dot.inactive { background-color: #dee2e6; color: #6c757d; }
    </style>
</head>

<body>

    <?php include("./includes/navbar.php"); ?>

    <div class="container bg-light py-4">

        <!-- Step indicator -->
        <div class="step-indicator justify-content-center mb-3">
            <div class="step-dot active">1</div>
            <div class="step-dot inactive">2</div>
            <div class="step-dot inactive">3</div>
            <div class="step-dot inactive">4</div>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="error-list">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- Left: Crop Area -->
            <div class="col-lg-8">
                <div class="card shadow-sm border-0">
                    <div class="card-body p-2">
                        <div class="crop-area">
                            <img id="image" src="<?= htmlspecialchars($displayPath) ?>" crossorigin="anonymous">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: Settings Panel -->
            <div class="col-lg-4">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body d-flex flex-column">
                        <h4 class="mb-2" data-i18n="crop.title">Crop & Choose Size</h4>
                        <p class="text-muted small" data-i18n="crop.subtitle">Select a plate format, then adjust the crop area.</p>

                        <!-- Plate Format Selection -->
                        <div class="mb-3">
                            <label class="form-label fw-bold" data-i18n="crop.plate_format">Plate Format</label>
                            <div class="d-grid gap-1">
                                <button type="button" class="btn btn-outline-secondary preset-btn active" id="btnSquare" onclick="setPlateFormat('square')">
                                    <strong data-i18n="crop.format_square">Square</strong>
                                    <small data-i18n="crop.format_square_hint">1:1 ratio - Classic mosaic format</small>
                                </button>
                                <button type="button" class="btn btn-outline-secondary preset-btn" id="btnLandscape" onclick="setPlateFormat('landscape')">
                                    <strong data-i18n="crop.format_landscape">Landscape 4:3</strong>
                                    <small data-i18n="crop.format_landscape_hint">Wider format - Great for scenery</small>
                                </button>
                                <button type="button" class="btn btn-outline-secondary preset-btn" id="btnPortrait" onclick="setPlateFormat('portrait')">
                                    <strong data-i18n="crop.format_portrait">Portrait 3:4</strong>
                                    <small data-i18n="crop.format_portrait_hint">Taller format - Perfect for portraits</small>
                                </button>
                                <button type="button" class="btn btn-outline-secondary preset-btn" id="btnFree" onclick="setPlateFormat('free')">
                                    <strong data-i18n="crop.format_free">Free / Custom</strong>
                                    <small data-i18n="crop.format_free_hint">Drag freely, then set dimensions manually</small>
                                </button>
                            </div>
                        </div>

                        <!-- Size Selection -->
                        <div class="mb-3">
                            <label class="form-label fw-bold" data-i18n="crop.size_label">Mosaic Size</label>
                            <div class="btn-group w-100 mb-2" role="group">
                                <button type="button" class="btn btn-outline-secondary" id="btnSmall" onclick="setSize('small')">
                                    <strong>S</strong><br><small>32</small>
                                </button>
                                <button type="button" class="btn btn-outline-secondary active" id="btnMedium" onclick="setSize('medium')">
                                    <strong>M</strong><br><small>64</small>
                                </button>
                                <button type="button" class="btn btn-outline-secondary" id="btnLarge" onclick="setSize('large')">
                                    <strong>L</strong><br><small>128</small>
                                </button>
                                <button type="button" class="btn btn-outline-secondary" id="btnCustomSize" onclick="setSize('custom')">
                                    <strong data-i18n="crop.size_custom">Custom</strong>
                                </button>
                            </div>

                            <div id="customSizeControls" class="row g-2" style="display:none;">
                                <div class="col">
                                    <label class="small fw-bold" data-i18n="dims.width">Width</label>
                                    <input type="number" id="customW" class="form-control form-control-sm" placeholder="64" min="16" max="512" step="16">
                                </div>
                                <div class="col">
                                    <label class="small fw-bold" data-i18n="dims.height">Height</label>
                                    <input type="number" id="customH" class="form-control form-control-sm" placeholder="64" min="16" max="512" step="16">
                                </div>
                                <div class="form-text small" data-i18n="dims.custom_hint">Multiples of 16. Max 512.</div>
                            </div>
                        </div>

                        <!-- Output dimensions display -->
                        <div class="size-info mb-3">
                            <span data-i18n="dims.output_label">Output:</span>
                            <strong id="displayDims">64 x 64</strong>
                            <span data-i18n="dims.studs_unit">studs</span>
                        </div>

                        <!-- Hidden form -->
                        <form method="POST" id="cropForm">
                            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_get()) ?>">
                            <input type="hidden" name="image" id="hiddenImage">
                            <input type="hidden" name="width" id="wInput" value="64">
                            <input type="hidden" name="height" id="hInput" value="64">
                        </form>

                        <div class="mt-auto pt-3 border-top d-flex justify-content-between align-items-center">
                            <a href="index.php" class="btn btn-outline-secondary" data-i18n="crop.cancel">Cancel</a>
                            <button id="btnSave" class="btn btn-primary btn-lg" data-i18n="crop.next">Next Step</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>
    <script>
        const image = document.getElementById('image');
        const form = document.getElementById('cropForm');
        const btnSave = document.getElementById('btnSave');
        const displayDims = document.getElementById('displayDims');
        const wInput = document.getElementById('wInput');
        const hInput = document.getElementById('hInput');
        let cropper;

        // State
        let currentFormat = 'square';
        let currentSizeMode = 'medium';
        let cropRatio = 1;

        // Plate format buttons
        const formatBtns = {
            square: document.getElementById('btnSquare'),
            landscape: document.getElementById('btnLandscape'),
            portrait: document.getElementById('btnPortrait'),
            free: document.getElementById('btnFree')
        };

        // Size buttons
        const sizeBtns = {
            small: document.getElementById('btnSmall'),
            medium: document.getElementById('btnMedium'),
            large: document.getElementById('btnLarge'),
            custom: document.getElementById('btnCustomSize')
        };

        const customSizeControls = document.getElementById('customSizeControls');
        const customW = document.getElementById('customW');
        const customH = document.getElementById('customH');

        // Initialize Cropper
        const startCropper = () => {
            if (cropper) cropper.destroy();
            cropper = new Cropper(image, {
                aspectRatio: 1,
                viewMode: 1,
                autoCropArea: 0.8,
                background: false,
                responsive: true,
            });
        };

        if (image.complete) startCropper();
        else image.onload = startCropper;

        // Plate format selection
        window.setPlateFormat = (format) => {
            currentFormat = format;

            // Update button states
            Object.values(formatBtns).forEach(btn => {
                btn.classList.remove('active', 'btn-secondary', 'text-white');
                btn.classList.add('btn-outline-secondary');
            });
            formatBtns[format].classList.remove('btn-outline-secondary');
            formatBtns[format].classList.add('active', 'btn-secondary', 'text-white');

            // Set cropper aspect ratio
            switch (format) {
                case 'square':    cropRatio = 1;     break;
                case 'landscape': cropRatio = 4/3;   break;
                case 'portrait':  cropRatio = 3/4;   break;
                case 'free':      cropRatio = NaN;   break;
            }
            if (cropper) cropper.setAspectRatio(cropRatio);

            calculateDimensions();
        };

        // Size selection
        window.setSize = (mode) => {
            currentSizeMode = mode;

            Object.values(sizeBtns).forEach(btn => btn.classList.remove('active'));
            sizeBtns[mode].classList.add('active');

            customSizeControls.style.display = (mode === 'custom') ? 'flex' : 'none';

            calculateDimensions();
        };

        // Calculate and display dimensions
        function calculateDimensions() {
            let w = 0, h = 0;

            if (currentSizeMode === 'small') {
                w = 32; h = 32;
            } else if (currentSizeMode === 'medium') {
                w = 64; h = 64;
            } else if (currentSizeMode === 'large') {
                w = 128; h = 128;
            } else if (currentSizeMode === 'custom') {
                w = parseInt(customW.value) || 64;
                h = parseInt(customH.value) || 64;
            }

            // Adjust for aspect ratio if not free
            if (currentFormat === 'landscape' && currentSizeMode !== 'custom') {
                // 4:3 - width is the base
                h = Math.round((w * 3 / 4) / 16) * 16;
                if (h < 16) h = 16;
            } else if (currentFormat === 'portrait' && currentSizeMode !== 'custom') {
                // 3:4 - height is the base
                h = w;
                w = Math.round((h * 3 / 4) / 16) * 16;
                if (w < 16) w = 16;
            }

            displayDims.textContent = w + ' x ' + h;
            wInput.value = w;
            hInput.value = h;
        }

        // Custom input handlers
        function enforce16(e) {
            let val = parseInt(e.target.value) || 16;
            val = Math.round(val / 16) * 16;
            if (val < 16) val = 16;
            if (val > 512) val = 512;
            e.target.value = val;
            calculateDimensions();
        }
        customW.addEventListener('change', enforce16);
        customH.addEventListener('change', enforce16);
        customW.addEventListener('keyup', calculateDimensions);
        customH.addEventListener('keyup', calculateDimensions);

        // Handle "Next" Button
        btnSave.addEventListener('click', () => {
            if (!cropper) return;

            const canvas = cropper.getCroppedCanvas({
                maxWidth: 2048,
                maxHeight: 2048
            });
            if (!canvas) return;

            const base64 = canvas.toDataURL('image/png', 0.9);
            document.getElementById('hiddenImage').value = base64;

            btnSave.disabled = true;
            if (window.I18N && typeof window.I18N.t === 'function') {
                btnSave.textContent = window.I18N.t('common.processing', 'Processing...');
            } else {
                btnSave.textContent = "Processing...";
            }
            form.submit();
        });

        // Initialize
        calculateDimensions();
    </script>

    <?php include("./includes/footer.php"); ?>
</body>

</html>
