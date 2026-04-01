<?php
require_once __DIR__ . '/config/session.php';
global $cnx;
include("./config/cnx.php");
require_once __DIR__ . '/includes/i18n.php';

$imgDir = __DIR__ . '/users/imgs';
$tilingDir = __DIR__ . '/users/tilings';
$_SESSION['redirect_after_login'] = 'index.php';
$errors = [];

if (isset($_GET['error'])) {
    if ($_GET['error'] === 'db_fail') {
        $errors[] = tr('errors.cart_db_fail', 'A database error occurred.');
    }
}

if (rand(1, 20) === 1) {
    cleanStorage($cnx, $imgDir, $tilingDir);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_SESSION['step0_image_id'])) {
        // Delete existing image tree to prevent orphans
        deleteDescendants($cnx, $_SESSION['step0_image_id'], $imgDir, $tilingDir, false);

        // Reset session variables
        unset($_SESSION['step0_image_id']);
        unset($_SESSION['step1_image_id']);
        unset($_SESSION['step2_image_id']);
        unset($_SESSION['step3_image_id']);
        unset($_SESSION['step4_image_id']);
        unset($_SESSION['target_width']);
        unset($_SESSION['target_height']);
    }

    if (!isset($_FILES["image"])) {
        http_response_code(400);
        $errors[] = "No file received.";
    } else {
        $img = $_FILES["image"];

        if ($img["error"] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            $errors[] = error_message($img['error']);
        }

        if (empty($errors)) {
            // Validate file extension against allowlist
            $allowedExtensions = ['png', 'jpg', 'jpeg', 'webp'];
            $fileExtension = strtolower(pathinfo($img['name'], PATHINFO_EXTENSION));
            if (!in_array($fileExtension, $allowedExtensions, true)) {
                http_response_code(400);
                $errors[] = "Invalid file extension.";
            }
        }

        if (empty($errors)) {
            // Enforce maximum file size
            if (!isset($img["size"]) || (int)$img["size"] > 2000000) {
                http_response_code(400);
                $errors[] = "The file size is too big (max 2MB).";
            }
        }

        if (empty($errors)) {
            // Verify image integrity and dimensions
            $size = @getimagesize($img["tmp_name"]);
            if ($size === false) {
                http_response_code(400);
                $errors[] = "Uploaded file is not a valid image.";
            } else {
                $width = (int)$size[0];
                $height = (int)$size[1];

                if ($width < 64 || $width > 4096 || $height < 64 || $height > 4096) {
                    http_response_code(400);
                    $errors[] = "Image dimensions must be between 64 and 4096 pixels.";
                }
            }
        }

        if (empty($errors)) {
            // Validate MIME type against allowlist
            $mimeType = @mime_content_type($img["tmp_name"]);
            $allowedMimes = ['image/png', 'image/jpeg', 'image/webp'];
            if (!is_string($mimeType) || !in_array($mimeType, $allowedMimes, true)) {
                http_response_code(400);
                $errors[] = "Invalid image MIME type.";
            }
        }

        if (empty($errors)) {
            if (!is_dir($imgDir)) {
                @mkdir($imgDir, 0700, true);
            }

            if (!is_dir($imgDir) || !is_writable($imgDir)) {

                http_response_code(500);
                $errors[] = "A server error occurred. Please contact support.";
            } else {

                $safeName = bin2hex(random_bytes(16)) . '.' . $fileExtension;
                $targetPath = $imgDir . '/' . $safeName;

                if (!move_uploaded_file($img["tmp_name"], $targetPath)) {
                    http_response_code(500);
                    $errors[] = "Failed to store the image. Please try again.";
                } else {

                    try {
                        // Assign NULL user ID for guests
                        $userId = $_SESSION['userId'] ?? NULL;
                        $width = (int)$size[0];
                        $height = (int)$size[1];

                        $stmt = $cnx->prepare("INSERT INTO IMAGE (user_id, filename, path, width, height, created_at, img_parent, status) VALUES (:userId, :filename, :path, :width, :height, NOW(), NULL, 'ORIGINAL')");
                        $stmt->bindParam(":userId", $userId);
                        $stmt->bindParam(":filename", $img['name']);
                        $stmt->bindParam(":path", $safeName);
                        $stmt->bindParam(":width", $width);
                        $stmt->bindParam(":height", $height);

                        $stmt->execute();

                        // Store image ID for next step
                        $_SESSION['step0_image_id'] = $cnx->lastInsertId();
                        $_SESSION['image_name'] = $img['name'];

                        // Redirect to crop selection
                        addLog($cnx, "USER", "IMPORT", "image");
                        header("Location: crop_selection.php");
                        exit;
                    } catch (PDOException $e) {
                        // Delete uploaded file on database failure
                        if (file_exists($targetPath)) {
                            unlink($targetPath);
                        }
                        $errors[] = "Database error. Please try again.";
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BrickMosaic - Transform Your Photos Into Brick Mosaics</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        /* ---- Slider Section ---- */
        .slider-section {
            padding: 3rem 5%;
            display: flex;
            justify-content: center;
            background: var(--section-alt-bg, #f5f7ff);
        }
        .slider-wrapper {
            width: 100%;
            max-width: 520px;
        }

        /* ---- Upload Section ---- */
        .upload-section {
            padding: 4rem 5%;
            text-align: center;
        }
        .upload-section .section-title {
            font-size: 2.2rem;
            font-weight: 800;
            margin-bottom: 1rem;
            color: var(--text-color, #1a1a2e);
        }
        .upload-subtitle {
            font-size: 1.1rem;
            color: var(--text-muted, #555);
            margin-bottom: 2rem;
            line-height: 1.6;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }
        .upload-section .upload-form {
            max-width: 500px;
            margin: 0 auto;
        }

        /* ---- Upload form ---- */
        .upload-form {
            margin-bottom: 1rem;
        }
        .drop-zone {
            border: 2px dashed var(--border-color, #c4c4c4);
            border-radius: 12px;
            padding: 2.5rem 1.5rem;
            text-align: center;
            cursor: pointer;
            transition: border-color .25s, background .25s;
            margin-bottom: 1rem;
            background: var(--card-bg, #fafafa);
        }
        .drop-zone:hover,
        .drop-zone.highlight {
            border-color: var(--accent, #4361ee);
            background: var(--accent-light, #eef1ff);
        }
        .drop-title {
            font-size: 1.1rem;
            margin: .6rem 0 .3rem;
        }
        .drop-hint {
            font-size: .88rem;
            color: var(--text-muted, #888);
        }
        .upload-icon-container::before {
            content: "";
            display: block;
            width: 48px;
            height: 48px;
            margin: 0 auto .4rem;
            background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke-width='1.5' stroke='%234361ee'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5'/%3E%3C/svg%3E") center/contain no-repeat;
        }
        .main-btn {
            display: block;
            width: 100%;
            padding: .9rem;
            font-size: 1.05rem;
            font-weight: 700;
            border: none;
            border-radius: 8px;
            background: var(--accent, #4361ee);
            color: #fff;
            cursor: pointer;
            transition: background .2s;
        }
        .main-btn:hover {
            background: var(--accent-hover, #3a56d4);
        }
        .supported-formats {
            text-align: center;
            font-size: .82rem;
            color: var(--text-muted, #999);
            margin-top: .7rem;
        }

        /* ---- Comparison slider ---- */
        .comparison-slider {
            position: relative;
            width: 100%;
            aspect-ratio: 1/1;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 8px 30px rgba(0,0,0,.12);
            --slider-pos: 50%;
        }
        .img-original,
        .img-mosaic {
            position: absolute;
            inset: 0;
            background-size: cover;
            background-position: center;
        }
        .img-mosaic {
            clip-path: inset(0 calc(100% - var(--slider-pos)) 0 0);
        }
        .slider-handle {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: ew-resize;
            z-index: 2;
        }
        .comparison-slider::after {
            content: "";
            position: absolute;
            top: 0;
            bottom: 0;
            left: var(--slider-pos);
            width: 3px;
            background: #fff;
            box-shadow: 0 0 6px rgba(0,0,0,.3);
            z-index: 1;
            pointer-events: none;
            transform: translateX(-50%);
        }
        .slider-circle {
            position: absolute;
            top: 50%;
            left: var(--slider-pos);
            transform: translate(-50%, -50%);
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #ffd100;
            box-shadow: 0 2px 8px rgba(0,0,0,.3);
            z-index: 3;
            pointer-events: none;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: #333;
        }

        /* ---- Error list ---- */
        .error-list {
            background: #fee;
            border: 1px solid #e88;
            border-radius: 8px;
            padding: .8rem 1.2rem;
            margin-bottom: 1.2rem;
            color: #b00;
        }
        .error-list ul {
            margin: 0;
            padding-left: 1.2rem;
        }

        /* ---- How It Works ---- */
        .how-it-works {
            padding: 5rem 5%;
            text-align: center;
            background: var(--section-alt-bg, #f5f7ff);
        }
        .how-it-works .section-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 3rem;
        }
        .steps-grid {
            display: flex;
            justify-content: center;
            gap: 2rem;
            flex-wrap: wrap;
        }
        .step-card {
            flex: 1 1 260px;
            max-width: 320px;
            background: var(--card-bg, #fff);
            border-radius: 14px;
            padding: 2rem 1.5rem;
            box-shadow: 0 4px 20px rgba(0,0,0,.06);
            transition: transform .2s;
        }
        .step-card:hover {
            transform: translateY(-4px);
        }
        .step-number {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: var(--accent, #4361ee);
            color: #fff;
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }
        .step-card h3 {
            font-size: 1.25rem;
            margin-bottom: .5rem;
        }
        .step-card p {
            font-size: .95rem;
            color: var(--text-muted, #666);
            line-height: 1.55;
        }

        /* ---- Features ---- */
        .features-section {
            padding: 5rem 5%;
            text-align: center;
        }
        .features-section .section-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 3rem;
        }
        .features-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.2rem;
            max-width: 960px;
            margin: 0 auto;
        }
        .feature-card {
            width: 100%;
            background: var(--card-bg, #fff);
            border-radius: 14px;
            padding: 2rem 1.4rem;
            box-shadow: 0 4px 20px rgba(0,0,0,.06);
            transition: transform .2s;
        }
        .feature-card:hover {
            transform: translateY(-4px);
        }
        .feature-icon {
            font-size: 2.2rem;
            margin-bottom: .8rem;
        }
        .feature-card h3 {
            font-size: 1.15rem;
            margin-bottom: .5rem;
        }
        .feature-card p {
            font-size: .9rem;
            color: var(--text-muted, #666);
            line-height: 1.55;
        }

        /* ---- CTA ---- */
        .cta-section {
            padding: 5rem 5%;
            text-align: center;
            background: var(--accent, #4361ee);
            color: #fff;
        }
        .cta-section h2 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
        }
        .cta-btn {
            display: inline-block;
            padding: 1rem 2.5rem;
            font-size: 1.1rem;
            font-weight: 700;
            border: 2px solid #fff;
            border-radius: 8px;
            background: transparent;
            color: #fff;
            cursor: pointer;
            transition: background .2s, color .2s;
            text-decoration: none;
        }
        .cta-btn:hover {
            background: #fff;
            color: var(--accent, #4361ee);
        }

        /* ---- Responsive ---- */
        @media (max-width: 900px) {
            .upload-section .section-title {
                font-size: 1.8rem;
            }
            .features-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        @media (max-width: 520px) {
            .features-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<?php include("./includes/navbar.php"); ?>

<!-- How It Works -->
<section class="how-it-works">
    <h2 class="section-title" data-i18n="index.how_title">How It Works</h2>
    <div class="steps-grid">
        <div class="step-card">
            <div class="step-number">1</div>
            <h3 data-i18n="index.step1_title">Upload</h3>
            <p data-i18n="index.step1_desc">Choose any photo from your device. We support JPG, PNG and WEBP formats.</p>
        </div>
        <div class="step-card">
            <div class="step-number">2</div>
            <h3 data-i18n="index.step2_title">Customize</h3>
            <p data-i18n="index.step2_desc">Crop, resize, apply filters and choose your preferred mosaic algorithm.</p>
        </div>
        <div class="step-card">
            <div class="step-number">3</div>
            <h3 data-i18n="index.step3_title">Order</h3>
            <p data-i18n="index.step3_desc">Preview your mosaic, adjust settings and order your custom brick set.</p>
        </div>
    </div>
</section>

<!-- Before / After Slider -->
<section class="slider-section">
    <div class="slider-wrapper">
        <div class="comparison-slider" id="mosaicSlider">
            <div class="img-original" style="background-image: url('assets/images/demo_orig.jpg');"></div>
            <div class="img-mosaic" id="mosaicOverlay" style="background-image: url('assets/images/demo_brick.jpg');"></div>
            <input type="range" min="0" max="100" value="50" class="slider-handle" id="sliderRange">
            <div class="slider-circle">&#x2B0C;</div>
        </div>
    </div>
</section>

<!-- Upload Section -->
<section class="upload-section" id="hero">
    <h2 id="start-creating" class="section-title" data-i18n="index.title">Transform Your Photos Into Brick Mosaics</h2>
    <p class="upload-subtitle" data-i18n="index.subtitle">Upload any image and watch it transform into a stunning brick mosaic artwork. Choose from multiple sizes, styles and algorithms.</p>

    <?php if (!empty($errors)): ?>
        <div class="error-list">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form action="" method="post" enctype="multipart/form-data" class="upload-form">
        <div id="dropArea" class="drop-zone">
            <div class="upload-icon-container"></div>
            <h5 class="drop-title" data-i18n="index.drop_title">Drag & Drop your image here</h5>
            <p class="drop-hint" data-i18n="index.drop_hint">or click to browse files</p>
        </div>

        <input type="file" id="imageUpload" name="image" accept="image/png,image/jpeg,image/webp" required style="display:none;">

        <button type="submit" class="main-btn" data-i18n="index.button">Upload & Continue</button>

        <div class="supported-formats" data-i18n="index.supported">
            Supported: JPG, PNG, WEBP (Max 2MB)
        </div>
    </form>
</section>

<!-- Features -->
<section class="features-section">
    <h2 class="section-title" data-i18n="index.features_title">Why Choose BrickMosaic?</h2>
    <div class="features-grid">
        <div class="feature-card">
            <div class="feature-icon">&#x1F3AF;</div>
            <h3 data-i18n="index.feat1_title">High Quality Results</h3>
            <p data-i18n="index.feat1_desc">Our smart processing adapts to your image to deliver the sharpest, most faithful brick mosaic possible.</p>
        </div>
        <div class="feature-card">
            <div class="feature-icon">&#x1F9E9;</div>
            <h3 data-i18n="index.feat2_title">Budget Friendly</h3>
            <p data-i18n="index.feat2_desc">Our system optimizes brick usage to keep costs down without sacrificing the look of your mosaic.</p>
        </div>
        <div class="feature-card">
            <div class="feature-icon">&#x1F50D;</div>
            <h3 data-i18n="index.feat3_title">Instant Preview</h3>
            <p data-i18n="index.feat3_desc">See exactly what your mosaic will look like before you buy. No surprises, no guesswork.</p>
        </div>
        <div class="feature-card">
            <div class="feature-icon">&#x1F4C4;</div>
            <h3 data-i18n="index.feat4_title">Easy to Build</h3>
            <p data-i18n="index.feat4_desc">Receive a clear, step-by-step PDF building guide. Anyone can assemble it, no experience needed.</p>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="cta-section">
    <h2 data-i18n="index.cta_title">Ready to create your masterpiece?</h2>
    <a href="#hero" class="cta-btn" id="ctaBtn" data-i18n="index.cta_button">Get Started</a>
</section>

<?php include("./includes/footer.php"); ?>

<script>
    // --- Drag & Drop Logic
    const dropArea = document.getElementById("dropArea");
    const fileInput = document.getElementById("imageUpload");

    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropArea.addEventListener(eventName, (e) => {
            e.preventDefault();
            e.stopPropagation();
        }, false);
    });

    ['dragenter', 'dragover'].forEach(eventName => {
        dropArea.addEventListener(eventName, () => {
            dropArea.classList.add('highlight');
        }, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropArea.addEventListener(eventName, () => {
            dropArea.classList.remove('highlight');
        }, false);
    });

    dropArea.addEventListener('drop', (e) => {
        const dt = e.dataTransfer;
        const files = dt.files;

        if (files && files.length > 0) {
            fileInput.files = files;
            updateDropArea(files[0].name);

            fileInput.dispatchEvent(new Event('change'));
        }
    }, false);

    dropArea.addEventListener("click", () => {
        fileInput.click();
    });

    fileInput.addEventListener("change", function() {
        if (this.files && this.files[0]) {
            updateDropArea(this.files[0].name);
        }
    });

    function updateDropArea(filename) {
        const title = dropArea.querySelector(".drop-title");
        const hint = dropArea.querySelector(".drop-hint");

        if(title) title.innerText = filename;
        if(hint) hint.innerText = "Fichier pr\u00eat ! Cliquez pour changer";

        dropArea.classList.add('highlight');
    }

    // Slider

    const slider = document.getElementById('sliderRange');
    const container = document.getElementById('mosaicSlider');

    slider.addEventListener('input', (e) => {
        container.style.setProperty('--slider-pos', e.target.value + '%');
    });

    // Smooth scroll for CTA
    document.getElementById('ctaBtn').addEventListener('click', function(e) {
        e.preventDefault();
        document.getElementById('hero').scrollIntoView({ behavior: 'smooth' });
    });
</script>
</body>
</html>