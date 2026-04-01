<?php
require_once __DIR__ . '/config/session.php';
global $cnx;
include("./config/cnx.php");
require_once __DIR__ . '/includes/i18n.php';

// Verify session prerequisites
if (!isset($_SESSION['step3_image_id'])) {
    header("Location: index.php");
    exit;
}

$parentId = $_SESSION['step3_image_id'];
$_SESSION['redirect_after_login'] = 'tiling_selection.php';
$imgFolder = 'users/imgs/';
$tilingFolder = 'users/tilings/';
$errors = [];
$previewImage = null;
$tilingStats = null;

if (isset($_GET['error'])) {
    if ($_GET['error'] === 'missing_files') {
        $errors[] = tr('errors.cart_missing_files', 'Required processing files are missing. Please regenerate the preview.');
    }
}

// Retrieve source image filename
$stmt = $cnx->prepare("SELECT path FROM IMAGE WHERE image_id = ?");
$stmt->execute([$parentId]);
$sourceFile = $stmt->fetchColumn();

// Process generation request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!csrf_validate($_POST['csrf'] ?? null)) {
        $errors[] = 'Invalid session.';
    } else {
        // Retrieve form inputs
        $method = $_POST['method'] ?? 'quadtree';
        $mode = $_POST['mode'] ?? 'relax';
        $threshold = (int)($_POST['threshold'] ?? 2000);

        $ALLOWED_TILES = [
            [1, 1], [1, 2], [1, 3], [1, 4], [1, 5], [1, 6], [1, 8], [1, 10], [1, 12],
            [2, 2], [2, 3], [2, 4], [2, 6], [2, 8], [2, 10], [2, 12], [2, 14], [2, 16],
            [3, 3], [4, 4], [4, 6], [4, 8], [4, 10], [4, 12],
            [6, 6], [6, 8], [6, 10], [6, 12], [6, 14], [6, 16], [6, 24],
            [8, 8], [8, 11], [8, 16], [16, 16]
        ];

        $tileSize = $_POST['tileSize'] ?? null;

        if ($method === 'tile') {
            if (!$tileSize || !preg_match('/^\d+x\d+$/', $tileSize)) {
                $errors[] = "Invalid tile size.";
            } else {
                [$tileWidth, $tileHeight] = array_map('intval', explode('x', $tileSize));

                $isAllowed = false;
                foreach ($ALLOWED_TILES as [$w, $h]) {
                    if ($w === $tileWidth && $h === $tileHeight) {
                        $isAllowed = true;
                        break;
                    }
                }

                if (!$isAllowed) {
                    $errors[] = "Tile size not allowed.";
                }
            }
        }

        $cmdArgs = [];

        switch ($method) {
            case '1x1':
                break;
            case 'quadtree':
                $cmdArgs[] = $threshold;
                break;
            case 'tile':
                $cmdArgs[] = $tileWidth;
                $cmdArgs[] = $tileHeight;
                $cmdArgs[] = $threshold;
                break;
        }

        // Handle atomic update logic
        $existingId = $_SESSION['step4_image_id'] ?? null;
        $isUpdate = false;
        $baseName = null;

        if ($existingId) {
            $stmt = $cnx->prepare("SELECT path FROM IMAGE WHERE image_id = ? AND img_parent = ?");
            $stmt->execute([$existingId, $parentId]);
            $existingRow = $stmt->fetch();
            if ($existingRow) {
                $isUpdate = true;
                $baseName = pathinfo($existingRow['path'], PATHINFO_FILENAME);
            }
        }

        if (!$baseName) {
            $baseName = bin2hex(random_bytes(16));
        }

        $finalPngName = $baseName;
        $finalTxtName = $baseName . '.txt';

        $inputPath    = __DIR__ . '/' . $imgFolder . $sourceFile;
        $outputPngPath = __DIR__ . '/' . $imgFolder . $finalPngName;
        $outputTxtPath = __DIR__ . '/' . $tilingFolder . $finalTxtName;

        $jarPath      = __DIR__ . '/brain.jar';
        $exePath      = __DIR__ . '/C_tiler';
        $catalogPath  = __DIR__ . '/catalog.txt';

        $javaCmd = 'java';
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $javaCmd = '"C:\\Program Files\\Eclipse Adoptium\\jdk-25.0.1.8-hotspot\\bin\\java.exe"';
            $exePath = __DIR__ . '/C_tiler';
        }

        $cmd = sprintf(
            '%s -cp .:%s fr.uge.univ_eiffel.TileAndDraw %s %s %s %s %s %s %s 2>&1',
            $javaCmd,
            escapeshellarg($jarPath),
            escapeshellarg($inputPath),
            escapeshellarg($outputPngPath),
            escapeshellarg($outputTxtPath),
            escapeshellarg($exePath),
            escapeshellarg($method),
            escapeshellarg($mode),
            implode(' ', array_map('escapeshellarg', $cmdArgs))
        );

        $output = [];
        $returnCode = 0;

        $oldDir = getcwd();
        chdir(__DIR__);
        exec($cmd, $output, $returnCode);
        chdir($oldDir);

        if ($returnCode === 0) {
            try {
                if ($isUpdate) {
                    $legoImageId = (int)$existingId;
                    $stmt = $cnx->prepare("UPDATE IMAGE SET status = 'LEGO' WHERE image_id = ?");
                    $stmt->execute([$existingId]);
                } else {
                    $stmt = $cnx->prepare("INSERT INTO IMAGE (user_id, filename, path, status, img_parent) VALUES (?, ?, ?, 'LEGO', ?)");
                    $userId = $_SESSION['userId'] ?? NULL;
                    $stmt->execute([$userId, 'Image Tilled', $finalPngName . ".png", $parentId]);
                    $legoImageId = (int)$cnx->lastInsertId();
                }

                $_SESSION['step4_image_id'] = $legoImageId;

                $stmt = $cnx->prepare("SELECT pavage_id FROM TILLING WHERE image_id = ?");
                $stmt->execute([$legoImageId]);
                $existingTiling = $stmt->fetchColumn();

                if ($existingTiling) {
                    $stmt = $cnx->prepare("UPDATE TILLING SET pavage_txt = ? WHERE image_id = ?");
                    $stmt->execute([$finalTxtName, $legoImageId]);
                } else {
                    $stmt = $cnx->prepare("INSERT INTO TILLING (image_id, pavage_txt) VALUES (?, ?)");
                    $stmt->execute([$legoImageId, $finalTxtName]);
                }

                $txtContent = file_get_contents($outputTxtPath);

                $pavageFile = __DIR__ . "/users/tilings/" . $finalTxtName;
                $_SESSION['pavage_txt_name'] = $finalTxtName;
                $_SESSION['pavage_txt'] = $pavageFile;

                $previewImage = $imgFolder . $finalPngName . ".png" . '?t=' . time();
                addLog($cnx, "USER", "GENERATE", "pavage");
            } catch (PDOException $e) {
                $errors[] = "A database error occurred. Please try again later.";
            }
        } else {
            $errors[] = "An error occurred during image processing. Please try again.";
        }
    }
} else {
    // Check for existing result on page load
    if (isset($_SESSION['step4_image_id'])) {
        $stmt = $cnx->prepare("SELECT path FROM IMAGE WHERE image_id = ?");
        $stmt->execute([$_SESSION['step4_image_id']]);
        $existingFile = $stmt->fetchColumn();
        if ($existingFile) {
            $previewImage = $imgFolder . $existingFile . '?t=' . time();
        }
        $finalTxtName = $_SESSION['pavage_txt_name'] ?? null;
    }
}

// Get tiling stats if preview exists
if ($previewImage && isset($finalTxtName)) {
    $tilingStats = getTilingStats($finalTxtName);
}

// Count bricks and colors from tiling file
$brickCount = 0;
$colorCount = 0;
if (isset($finalTxtName)) {
    $txtPath = __DIR__ . "/users/tilings/" . $finalTxtName;
    if (file_exists($txtPath)) {
        $lines = file($txtPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines && count($lines) > 1) {
            $brickCount = count($lines) - 1; // first line is stats
            $colors = [];
            for ($i = 1; $i < count($lines); $i++) {
                $parts = explode('/', $lines[$i]);
                if (count($parts) >= 2) {
                    $colorPart = explode(',', $parts[1]);
                    $colors[$colorPart[0]] = true;
                }
            }
            $colorCount = count($colors);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(tr('tiling.page_title', 'Step 4: Generate Mosaic')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .preview-box {
            background-color: #212529;
            width: 100%;
            min-height: 300px;
            padding: 10px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .lego-img {
            max-width: 100%;
            height: auto;
            object-fit: contain;
            image-rendering: pixelated;
            cursor: zoom-in;
        }

        #imgModal {
            display: none;
            position: fixed;
            z-index: 1050;
            padding-top: 50px;
            left: 0; top: 0;
            width: 100%; height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.9);
        }
        .modal-content {
            margin: auto;
            display: block;
            width: 80%;
            max-width: 1000px;
            image-rendering: pixelated;
        }
        .close {
            position: absolute;
            top: 15px; right: 35px;
            color: #f1f1f1;
            font-size: 40px;
            font-weight: bold;
            cursor: pointer;
        }

        .btn-check:checked+.btn-outline-primary {
            background-color: #0d6efd;
            color: white;
            border-color: #0d6efd;
        }

        .preset-btn {
            text-align: left;
            position: relative;
        }
        .preset-btn small {
            display: block;
            font-size: 0.75rem;
            opacity: 0.8;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            margin-bottom: 16px;
        }
        .stat-card {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 10px;
            text-align: center;
        }
        .stat-value {
            font-size: 1.3rem;
            font-weight: 800;
            color: #d11212;
        }
        .stat-label {
            font-size: 0.75rem;
            color: #6c757d;
            text-transform: uppercase;
        }

        .add-cart-btn {
            background-color: #198754;
            color: white;
            border: none;
            border-radius: 10px;
            padding: 16px 24px;
            font-size: 1.2rem;
            font-weight: 800;
            text-transform: uppercase;
            cursor: pointer;
            box-shadow: 0 4px 0 #146c43;
            transition: all 0.15s;
            display: block;
            width: 100%;
            text-align: center;
            text-decoration: none;
        }
        .add-cart-btn:hover {
            background-color: #157347;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 6px 0 #146c43;
        }
        .add-cart-btn:active {
            transform: translateY(2px);
            box-shadow: 0 2px 0 #146c43;
        }
        .add-cart-btn:disabled, .add-cart-btn.disabled {
            opacity: 0.45;
            cursor: not-allowed;
            transform: none;
            box-shadow: 0 4px 0 #146c43;
        }

        .algo-help {
            font-size: 0.8rem;
            color: #6c757d;
            background: #f8f9fa;
            border-radius: 6px;
            padding: 8px 10px;
            margin-top: 8px;
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
        .step-dot.done { background-color: #198754; }
        .step-dot.inactive { background-color: #dee2e6; color: #6c757d; }
    </style>
</head>

<body>

    <?php include("./includes/navbar.php"); ?>

    <div class="container bg-light py-4">

        <!-- Step indicator -->
        <div class="step-indicator justify-content-center mb-3">
            <div class="step-dot done">1</div>
            <div class="step-dot done">2</div>
            <div class="step-dot done">3</div>
            <div class="step-dot active">4</div>
        </div>

        <div class="row justify-content-center">
            <div class="col-lg-11">
                <div class="card shadow border-0">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0" data-i18n="tiling.header">Step 4: Generate Your Mosaic</h5>
                    </div>
                    <div class="card-body p-4">

                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0 ps-3">
                                    <?php foreach ($errors as $e) echo "<li>" . htmlspecialchars($e) . "</li>"; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <div class="row g-4">
                            <!-- Left: Preview + Stats + Cart Button -->
                            <div class="col-md-6">
                                <div class="preview-box shadow-sm mb-3">
                                    <?php if ($previewImage): ?>
                                        <img src="<?= $previewImage ?>" class="lego-img" onclick="openModal(this.src)" alt="Mosaic Preview">
                                    <?php else: ?>
                                        <div class="text-white text-center p-3">
                                            <p class="mb-0 opacity-75" data-i18n="tiling.preview_placeholder">Preview will appear here after generation</p>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <?php if ($previewImage && $tilingStats): ?>
                                    <!-- Stats Grid -->
                                    <div class="stats-grid">
                                        <div class="stat-card">
                                            <div class="stat-value"><?= number_format((float)$tilingStats['price'] / 100, 2, '.', ',') ?>&euro;</div>
                                            <div class="stat-label" data-i18n="tiling.stat_price">Price</div>
                                        </div>
                                        <div class="stat-card">
                                            <div class="stat-value"><?= number_format($brickCount, 0, '.', ',') ?></div>
                                            <div class="stat-label" data-i18n="tiling.stat_bricks">Bricks</div>
                                        </div>
                                        <div class="stat-card">
                                            <div class="stat-value"><?= number_format($colorCount) ?></div>
                                            <div class="stat-label" data-i18n="tiling.stat_colors">Colors</div>
                                        </div>
                                    </div>

                                    <div class="text-center mb-2">
                                        <span class="badge bg-info fs-6"><?= htmlspecialchars($tilingStats['quality'] ?? '') ?>% quality</span>
                                    </div>

                                    <!-- BIG Add to Cart Button -->
                                    <a href="add_cart.php" class="add-cart-btn" data-i18n="tiling.add_to_cart">
                                        Add to Cart - <?= number_format((float)$tilingStats['price'] / 100, 2, '.', ',') ?>&euro;
                                    </a>
                                <?php else: ?>
                                    <button class="add-cart-btn disabled" disabled data-i18n="tiling.generate_first">
                                        <?= htmlspecialchars(tr('tiling.generate_first', 'Generate a preview first')) ?>
                                    </button>
                                <?php endif; ?>
                            </div>

                            <!-- Right: Algorithm Selection -->
                            <div class="col-md-6 d-flex flex-column">
                                <form method="POST" id="tilingForm" class="flex-grow-1">
                                    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_get()) ?>">

                                    <h6 class="fw-bold mb-2" data-i18n="tiling.step_method">1. Choose Algorithm</h6>

                                    <div class="mb-3">
                                        <select id="algorithmSelect" name="method" class="form-select" onchange="toggleAlgorithmParams()">
                                            <option value="1x1" data-i18n="tiling.algo_1x1"><?= htmlspecialchars(tr('tiling.algo_1x1', '1x1 - Single studs only (simplest)')) ?></option>
                                            <option value="quadtree" selected data-i18n="tiling.algo_quadtree"><?= htmlspecialchars(tr('tiling.algo_quadtree', 'Quadtree - Smart subdivision (recommended)')) ?></option>
                                            <option value="tile" data-i18n="tiling.algo_tile"><?= htmlspecialchars(tr('tiling.algo_tile', 'Tile - Fixed brick sizes')) ?></option>
                                        </select>
                                        <div class="algo-help" id="algoHelp">
                                            Quadtree recursively divides the image into blocks. Areas with similar colors use larger bricks, saving cost while preserving detail.
                                        </div>
                                    </div>

                                    <input type="hidden" name="threshold" id="thresholdInput" value="2000">

                                    <div id="algoParams" class="mb-3">
                                        <!-- Dynamic inputs injected by JS -->
                                    </div>

                                    <h6 class="fw-bold mb-2" data-i18n="tiling.mode_label">2. Mode</h6>
                                    <div class="mb-3">
                                        <div class="btn-group w-100" role="group">
                                            <input type="radio" class="btn-check" name="mode" id="modeRelax" value="relax" checked>
                                            <label class="btn btn-outline-secondary" for="modeRelax">
                                                <strong>Relax</strong><br>
                                                <small data-i18n="tiling.mode_relax_hint">Flexible brick placement</small>
                                            </label>
                                            <input type="radio" class="btn-check" name="mode" id="modeStrict" value="strict">
                                            <label class="btn btn-outline-secondary" for="modeStrict">
                                                <strong>Strict</strong><br>
                                                <small data-i18n="tiling.mode_strict_hint">Exact tile adherence</small>
                                            </label>
                                        </div>
                                    </div>

                                    <div class="mt-3 pt-3 border-top">
                                        <button type="submit" class="btn btn-primary w-100 btn-lg mb-3">
                                            <?php if ($previewImage): ?>
                                                <span data-i18n="tiling.regenerate">Regenerate Preview</span>
                                            <?php else: ?>
                                                <span data-i18n="tiling.generate">Generate Preview</span>
                                            <?php endif; ?>
                                        </button>

                                        <a href="filter_selection.php" class="btn btn-outline-secondary w-100" data-i18n="tiling.back">Back to Filters</a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="imgModal" onclick="closeModal()">
        <span class="close">&times;</span>
        <img class="modal-content" id="modalImg">
    </div>

    <script>
        const thresholdInput = document.getElementById('thresholdInput');
        const algoHelp = document.getElementById('algoHelp');
        let presetBtns, customDiv, customNum;

        const algoDescriptions = {
            '1x1': <?= json_encode(tr('tiling.algo_desc_1x1', 'Each pixel becomes a single 1x1 stud. Maximum detail but highest brick count and cost.')) ?>,
            'quadtree': <?= json_encode(tr('tiling.algo_desc_quadtree', 'Recursively divides the image into blocks. Areas with similar colors use larger bricks, saving cost while preserving detail.')) ?>,
            'tile': <?= json_encode(tr('tiling.algo_desc_tile', 'Uses fixed-size bricks to fill the mosaic. Choose the brick dimensions for a uniform look.')) ?>
        };

        function toggleAlgorithmParams() {
            const algo = document.getElementById('algorithmSelect').value;
            const container = document.getElementById('algoParams');
            container.innerHTML = '';

            algoHelp.textContent = algoDescriptions[algo] || '';

            if (algo === 'quadtree') {
                container.innerHTML = `
                <h6 class="fw-bold mb-2" data-i18n="tiling.step_budget">Detail Level</h6>
                <div class="d-grid gap-1 mb-2">
                    <button type="button" class="btn btn-outline-secondary preset-btn" onclick="setThreshold(1000, this)">
                        <strong data-i18n="tiling.preset_high">High Detail</strong>
                        <small data-i18n="tiling.preset_high_hint">More bricks, better fidelity (threshold: 1,000)</small>
                    </button>
                    <button type="button" class="btn btn-outline-secondary preset-btn active" onclick="setThreshold(2000, this)">
                        <strong data-i18n="tiling.preset_balanced">Balanced</strong>
                        <small data-i18n="tiling.preset_balanced_hint">Best quality/cost ratio (threshold: 2,000)</small>
                    </button>
                    <button type="button" class="btn btn-outline-secondary preset-btn" onclick="setThreshold(100000, this)">
                        <strong data-i18n="tiling.preset_minimal">Abstract / Low Cost</strong>
                        <small data-i18n="tiling.preset_minimal_hint">Fewer bricks, artistic look (threshold: 100,000)</small>
                    </button>
                    <button type="button" class="btn btn-outline-secondary preset-btn" id="customBtn" onclick="enableCustom()">
                        <strong data-i18n="tiling.preset_custom">Custom Value</strong>
                        <small data-i18n="tiling.preset_custom_hint">Enter your own threshold</small>
                    </button>
                </div>
                <div class="collapse" id="customInputDiv">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text" data-i18n="tiling.custom_value">Threshold</span>
                        <input type="number" class="form-control" id="customNumber" placeholder="e.g. 5000">
                        <button class="btn btn-primary" type="button" onclick="applyCustom()" data-i18n="tiling.custom_set">Set</button>
                    </div>
                </div>`;
                presetBtns = container.querySelectorAll('.preset-btn');
                customDiv = document.getElementById('customInputDiv');
                customNum = document.getElementById('customNumber');
                customNum.addEventListener('input', (e) => { thresholdInput.value = e.target.value; });
            } else if (algo === 'tile') {
                container.innerHTML = `
                <h6 class="fw-bold mb-2" data-i18n="tiling.brick_size">${<?= json_encode(tr('tiling.brick_size', 'Brick Size')) ?>}</h6>
                <select name="tileSize" class="form-select form-select-sm mb-2" required>
                    <option value="" data-i18n="tiling.choose_brick">${<?= json_encode(tr('tiling.choose_brick', '-- Choose a brick size --')) ?>}</option>
                    <optgroup label="${<?= json_encode(tr('tiling.small_bricks', 'Small bricks')) ?>}">
                        <option value="1x1">1 x 1</option>
                        <option value="1x2">1 x 2</option>
                        <option value="1x3">1 x 3</option>
                        <option value="1x4">1 x 4</option>
                        <option value="2x2">2 x 2</option>
                        <option value="2x3">2 x 3</option>
                        <option value="2x4">2 x 4</option>
                    </optgroup>
                    <optgroup label="${<?= json_encode(tr('tiling.medium_bricks', 'Medium bricks')) ?>}">
                        <option value="4x4">4 x 4</option>
                        <option value="4x6">4 x 6</option>
                        <option value="4x8">4 x 8</option>
                        <option value="6x6">6 x 6</option>
                        <option value="6x8">6 x 8</option>
                    </optgroup>
                    <optgroup label="${<?= json_encode(tr('tiling.large_plates', 'Large plates')) ?>}">
                        <option value="8x8">8 x 8</option>
                        <option value="8x16">8 x 16</option>
                        <option value="16x16">16 x 16</option>
                    </optgroup>
                </select>
                <label class="small fw-bold mt-1" data-i18n="tiling.threshold">${<?= json_encode(tr('tiling.threshold', 'Threshold')) ?>}</label>
                <input type="number" name="threshold" class="form-control form-control-sm" value="2000" min="1">`;
            }
        }

        document.addEventListener('DOMContentLoaded', toggleAlgorithmParams);

        function setThreshold(val, btn) {
            thresholdInput.value = val;
            if (customDiv) customDiv.classList.remove('show');
            presetBtns.forEach(b => {
                b.classList.remove('active', 'btn-secondary', 'text-white');
                b.classList.add('btn-outline-secondary');
            });
            btn.classList.remove('btn-outline-secondary');
            btn.classList.add('active', 'btn-secondary', 'text-white');
        }

        function enableCustom() {
            presetBtns.forEach(b => {
                b.classList.remove('active', 'btn-secondary', 'text-white');
                b.classList.add('btn-outline-secondary');
            });
            var customBtn = document.getElementById('customBtn');
            customBtn.classList.remove('btn-outline-secondary');
            customBtn.classList.add('active', 'btn-secondary', 'text-white');
            customDiv.classList.add('show');
            customNum.focus();
        }

        function applyCustom() {
            var val = customNum.value;
            if (val && val > 0) {
                thresholdInput.value = val;
            } else {
                alert("Please enter a valid number");
            }
        }

        function openModal(src) {
            document.getElementById("imgModal").style.display = "block";
            document.getElementById("modalImg").src = src;
        }

        function closeModal() {
            document.getElementById("imgModal").style.display = "none";
        }
    </script>

    <?php include("./includes/footer.php"); ?>
</body>

</html>
