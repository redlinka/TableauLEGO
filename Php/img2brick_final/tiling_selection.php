<?php
session_start();
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
            [1, 1],
            [1, 2],
            [1, 3],
            [1, 4],
            [1, 5],
            [1, 6],
            [1, 8],
            [1, 10],
            [1, 12],
            [2, 2],
            [2, 3],
            [2, 4],
            [2, 6],
            [2, 8],
            [2, 10],
            [2, 12],
            [2, 14],
            [2, 16],
            [3, 3],
            [4, 4],
            [4, 6],
            [4, 8],
            [4, 10],
            [4, 12],
            [6, 6],
            [6, 8],
            [6, 10],
            [6, 12],
            [6, 14],
            [6, 16],
            [6, 24],
            [8, 8],
            [8, 11],
            [8, 16],
            [16, 16]
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

        // Check for existing step to update
        if ($existingId) {
            $stmt = $cnx->prepare("SELECT path FROM IMAGE WHERE image_id = ? AND img_parent = ?");
            $stmt->execute([$existingId, $parentId]);
            $existingRow = $stmt->fetch();
            if ($existingRow) {
                $isUpdate = true;
                // Extract base filename
                $baseName = pathinfo($existingRow['path'], PATHINFO_FILENAME);
            }
        }

        // Generate unique filename if new
        if (!$baseName) {
            $baseName = bin2hex(random_bytes(16));
        }

        // Define file paths
        $finalPngName = $baseName; //. '.png';
        $finalTxtName = $baseName . '.txt';

        $inputPath    = __DIR__ . '/' . $imgFolder . $sourceFile;
        $outputPngPath = __DIR__ . '/' . $imgFolder . $finalPngName;
        $outputTxtPath = __DIR__ . '/' . $tilingFolder . $finalTxtName;

        $jarPath      = __DIR__ . '/brain.jar';
        $exePath      = __DIR__ . '/C_tiler';
        $catalogPath  = __DIR__ . '/catalog.txt';

        // Detect Java executable (if the code is runnning on my personnal machine or the server)
        $javaCmd = 'java';
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $javaCmd = '"C:\\Program Files\\Eclipse Adoptium\\jdk-25.0.1.8-hotspot\\bin\\java.exe"';
            $exePath      = __DIR__ . '/C_tiler';
        }

        // Execute Java tiling application
        // Usage: <inPng> <outPng> <outTxt> <catalog> <exe> <method> <thresh>
        $cmd = sprintf(
            '%s -cp %s fr.uge.univ_eiffel.TileAndDraw %s %s %s %s %s %s %s 2>&1',
            $javaCmd,
            escapeshellarg($jarPath),
            escapeshellarg($inputPath),     // 0
            escapeshellarg($outputPngPath), // 1
            escapeshellarg($outputTxtPath), // 2
            escapeshellarg($exePath),       // 3
            escapeshellarg($method),        // 4
            escapeshellarg($mode),          // 5
            implode(' ', array_map('escapeshellarg', $cmdArgs)) // 6+
        );

        $output = [];
        $returnCode = 0;


        exec($cmd, $output, $returnCode);

        // foreach ($output as $o) {
        //     echo $o . "\n";
        // }

        if ($returnCode === 0) {
            // Persist results to database
            try {
                if ($isUpdate) {
                    // Update existing image record
                    $stmt = $cnx->prepare("UPDATE IMAGE SET status = 'LEGO' WHERE image_id = ?");
                    $stmt->execute([$existingId]);
                } else {
                    // Insert new image record
                    $stmt = $cnx->prepare("INSERT INTO IMAGE (user_id, filename, path, status, img_parent) VALUES (?, ?, ?, 'LEGO', ?)");
                    $userId = $_SESSION['userId'] ?? NULL;
                    $stmt->execute([$userId, 'Image Tilled', $finalPngName . ".png", $parentId]);
                    $_SESSION['step4_image_id'] = $cnx->lastInsertId();
                }

                $legoImageId = (int)$_SESSION['step4_image_id'];
                $txtContent = file_get_contents($outputTxtPath);

                $legoImageId = (int)$_SESSION['step4_image_id'];
                $pavageFile = $finalTxtName;

                $stmt = $cnx->prepare("SELECT pavage_id FROM TILLING WHERE image_id = ? LIMIT 1");
                $stmt->execute([$legoImageId]);
                $pavageId = $stmt->fetchColumn();

                if ($pavageId) {
                    $stmt = $cnx->prepare("UPDATE TILLING SET pavage_txt = ? WHERE image_id = ?");
                    $stmt->execute([$pavageFile, $legoImageId]);
                } else {
                    $stmt = $cnx->prepare("INSERT INTO TILLING (image_id, pavage_txt) VALUES (?, ?)");
                    $stmt->execute([$legoImageId, $pavageFile]);
                }

                $previewImage = $imgFolder . $finalPngName . ".png" . '?t=' . time(); // add .png

            } catch (PDOException $e) {
                $errors[] = "A database error occurred. Please try again later.";
            }
        } else {
            $errors[] = "An error occurred during image processing. Please try again.";
            //$errors[] = "Java/C Error :" . $javaCmd; // in dev
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
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars(tr('tiling.page_title', 'Step 4: Generate LEGO')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* Container: flexible height, no forced square */
        .preview-box {
            background-color: #212529;
            width: 100%;
            min-height: 300px;
            /* Minimum height so it doesn't collapse if empty */
            padding: 10px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Image: Natural aspect ratio, zoomable */
        .lego-img {
            max-width: 100%;
            height: auto;
            /* This keeps the original aspect ratio */
            object-fit: contain;
            image-rendering: pixelated;
            cursor: zoom-in;
        }

        /* Modal Styles (Same as above) */
        #imgModal {
            display: none;
            position: fixed;
            z-index: 1050;
            padding-top: 50px;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
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
            top: 15px;
            right: 35px;
            color: #f1f1f1;
            font-size: 40px;
            font-weight: bold;
            cursor: pointer;
        }

        /* Custom Button Styling */
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
    </style>
</head>

<body>

    <?php include("./includes/navbar.php"); ?>

    <div class="container bg-light py-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card shadow border-0">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0" data-i18n="tiling.header">Step 4: Tiling Optimization</h5>
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
                            <div class="col-md-6">
                                <div class="preview-box shadow-sm">
                                    <?php if ($previewImage): ?>
                                        <img src="<?= $previewImage ?>" class="lego-img" onclick="openModal(this.src)" alt="">
                                    <?php else: ?>
                                        <div class="text-white text-center p-3">
                                            <p class="mb-0 opacity-75" data-i18n="tiling.preview_placeholder">Preview will appear here</p>
                                        </div>
                                    <?php endif; ?>
                                </div>

                            </div>

                            <div class="col-md-6 d-flex flex-column">
                                <form method="POST" id="tilingForm" class="flex-grow-1">
                                    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_get()) ?>">

                                    <h6 class="fw-bold mb-3" data-i18n="tiling.step_method">1. Select Method</h6>

                                    <div class="mb-3">
                                        <select id="algorithmSelect" name="method" class="form-select" onchange="toggleAlgorithmParams()">
                                            <option value="1x1">1x1 (No extra args)</option>
                                            <option value="quadtree">Quadtree (Threshold)</option>
                                            <option value="tile">Tile (Width, Height, Threshold)</option>
                                        </select>
                                    </div>

                                    <div id="thresholdSection">

                                        <input type="hidden" name="threshold" id="thresholdInput" value="2000">

                                        <div id="algoParams" class="mb-3">
                                            <!-- Dynamic inputs -->
                                        </div>
                                    </div>

                                    <h6 class="fw-bold mb-3">Mode</h6>
                                    <div class="mb-3">
                                        <select name="mode" class="form-select">
                                            <option value="relax">Relax</option>
                                            <option value="strict">Strict</option>
                                        </select>
                                    </div>


                                    <div class="mt-4 pt-3 border-top">

                                        <?php if ($previewImage): ?>
                                            <p class="fw-bold mb-3"> <?= number_format((float)getTilingStats($pavageFile)['price'], 2, ".", " ") . '€' ?></p>
                                            <button type="submit" class="btn btn-primary w-100 btn-lg mb-3" data-i18n="tiling.regenerate">Regenerate Preview</button>
                                        <?php else: ?>
                                            <button type="submit" class="btn btn-primary w-100 btn-lg mb-3" data-i18n="tiling.generate">Generate Preview</button>
                                        <?php endif; ?>

                                        <div class="d-flex justify-content-between align-items-center">
                                            <a href="filter_selection.php" class="btn btn-outline-secondary" data-i18n="tiling.back">Back</a>

                                            <?php if ($previewImage): ?>
                                                <a href="add_cart.php" class="btn btn-success fw-bold" data-i18n="tiling.finalize">Add to basket</a>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-secondary" data-i18n="tiling.finalize" disabled>Add to basket</button>
                                            <?php endif; ?>
                                        </div>

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
        const thresholdSection = document.getElementById('thresholdSection');
        const thresholdInput = document.getElementById('thresholdInput');


        function toggleAlgorithmParams() {
            const algo = document.getElementById('algorithmSelect').value;
            const container = document.getElementById('algoParams');
            container.innerHTML = ''; // reset

            if (algo === 'quadtree') {
                container.innerHTML = `
                <h6 class="fw-bold mb-3" data-i18n="tiling.step_budget">2. Select Budget / Precision</h6>
                <div class="d-grid gap-2 mb-3">
                    <button type="button" class="btn btn-outline-secondary preset-btn" onclick="setThreshold(1000, this)">
                        <strong data-i18n="tiling.preset_high">High Detail</strong>
                        <small data-i18n="tiling.preset_high_hint">Threshold: 1,000</small>
                    </button>

                    <button type="button" class="btn btn-outline-secondary preset-btn active" onclick="setThreshold(2000, this)">
                        <strong data-i18n="tiling.preset_balanced">Balanced</strong>
                        <small data-i18n="tiling.preset_balanced_hint">Threshold: 2,000 (Recommended)</small>
                    </button>

                    <button type="button" class="btn btn-outline-secondary preset-btn" onclick="setThreshold(100000, this)">
                        <strong data-i18n="tiling.preset_minimal">Minimal Price</strong>
                        <small data-i18n="tiling.preset_minimal_hint">Threshold: 100,000 (Abstract)</small>
                    </button>

                    <button type="button" class="btn btn-outline-secondary preset-btn" id="customBtn" onclick="enableCustom()">
                        <strong data-i18n="tiling.preset_custom">Custom Value</strong>
                        <small data-i18n="tiling.preset_custom_hint">Enter manually...</small>
                    </button>
                </div>

                <div class="collapse" id="customInputDiv">
                    <div class="input-group">
                        <span class="input-group-text" data-i18n="tiling.custom_value">Value</span>
                        <input type="number" class="form-control" id="customNumber" placeholder="e.g. 5000" data-i18n-attr="placeholder:tiling.custom_placeholder">
                        <button class="btn btn-primary" type="button" onclick="applyCustom()" data-i18n="tiling.custom_set">Set</button>
                    </div>
                </div>
        `;
                presetBtns = container.querySelectorAll('.preset-btn');
                customDiv = document.getElementById('customInputDiv');
                customNum = document.getElementById('customNumber');

                customNum.addEventListener('input', (e) => {
                    thresholdInput.value = e.target.value;
                });
            } else if (algo === 'tile') {
                container.innerHTML = `
                <h6 class="fw-bold mb-3">2. Select Tile Size</h6>
                <select name="tileSize" class="form-select" required>
                    <option value="">-- Choose a tile size --</option>

                    <!-- 1x -->
                    <option value="1x1">1 × 1</option>
                    <option value="1x2">1 × 2</option>
                    <option value="1x3">1 × 3</option>
                    <option value="1x4">1 × 4</option>
                    <option value="1x5">1 × 5</option>
                    <option value="1x6">1 × 6</option>
                    <option value="1x8">1 × 8</option>
                    <option value="1x10">1 × 10</option>
                    <option value="1x12">1 × 12</option>

                    <!-- 2x -->
                    <option value="2x2">2 × 2</option>
                    <option value="2x3">2 × 3</option>
                    <option value="2x4">2 × 4</option>
                    <option value="2x6">2 × 6</option>
                    <option value="2x8">2 × 8</option>
                    <option value="2x10">2 × 10</option>
                    <option value="2x12">2 × 12</option>
                    <option value="2x14">2 × 14</option>
                    <option value="2x16">2 × 16</option>

                    <!-- 3x -->
                    <option value="3x3">3 × 3</option>

                    <!-- 4x -->
                    <option value="4x4">4 × 4</option>
                    <option value="4x6">4 × 6</option>
                    <option value="4x8">4 × 8</option>
                    <option value="4x10">4 × 10</option>
                    <option value="4x12">4 × 12</option>

                    <!-- 6x -->
                    <option value="6x6">6 × 6</option>
                    <option value="6x8">6 × 8</option>
                    <option value="6x10">6 × 10</option>
                    <option value="6x12">6 × 12</option>
                    <option value="6x14">6 × 14</option>
                    <option value="6x16">6 × 16</option>
                    <option value="6x24">6 × 24</option>

                    <!-- 8x -->
                    <option value="8x8">8 × 8</option>
                    <option value="8x11">8 × 11</option>
                    <option value="8x16">8 × 16</option>

                    <!-- 16x -->
                    <option value="16x16">16 × 16</option>
                </select>

                <label class="mt-3">Threshold</label>
                <input
                    type="number"
                    name="threshold"
                    class="form-control"
                    value="2000"
                    min="1"
                />

                    `;
            }
        }

        // Init on page load
        document.addEventListener('DOMContentLoaded', toggleAlgorithmParams);

        // Handle threshold preset selection
        function setThreshold(val, btn) {
            // Set Value
            thresholdInput.value = val;

            // Hide custom input
            customDiv.classList.remove('show');

            // Update button states
            presetBtns.forEach(b => b.classList.remove('active', 'btn-secondary', 'text-white'));
            presetBtns.forEach(b => b.classList.add('btn-outline-secondary')); // Reset all to outline

            btn.classList.remove('btn-outline-secondary');
            btn.classList.add('active', 'btn-secondary', 'text-white');
        }

        // Enable custom threshold mode
        function enableCustom() {
            // Reset preset buttons
            presetBtns.forEach(b => b.classList.remove('active', 'btn-secondary', 'text-white'));
            presetBtns.forEach(b => b.classList.add('btn-outline-secondary'));

            // Highlight custom button
            document.getElementById('customBtn').classList.remove('btn-outline-secondary');
            document.getElementById('customBtn').classList.add('active', 'btn-secondary', 'text-white');

            // Display custom input
            customDiv.classList.add('show');
            customNum.focus();
        }

        // Apply custom threshold
        function applyCustom() {
            const val = customNum.value;
            if (val && val > 0) {
                thresholdInput.value = val;
            } else {
                if (window.I18N && typeof window.I18N.t === 'function') {
                    alert(window.I18N.t('tiling.valid_number', 'Please enter a valid number'));
                } else {
                    alert("Please enter a valid number");
                }
            }
        }

        function openModal(src) {
            document.getElementById("imgModal").style.display = "block";
            document.getElementById("modalImg").src = src;
        }

        function closeModal() {
            document.getElementById("imgModal").style.display = "none";
        }

        // Synchronize custom input with hidden field
        customNum.addEventListener('input', (e) => {
            thresholdInput.value = e.target.value;
        });

        // Initialize UI state
        toggleThresholds();
    </script>

    <?php include("./includes/footer.php"); ?>
</body>

</html>