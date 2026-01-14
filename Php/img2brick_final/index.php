<?php
session_start();
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
            $allowedExtensions = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
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
            $allowedMimes = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];
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
                        //$errors[] = "Database error: " . $e->getMessage();
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
    <title>Upload Image - TableauLEGO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* Modern Drag & Drop Styling */
        #dropArea {
            border: 2px dashed #adb5bd;
            background-color: #f8f9fa;
            border-radius: 12px;
            padding: 3rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 1.5rem;
            position: relative;
        }

        #dropArea:hover,
        #dropArea.highlight {
            border-color: #0d6efd;
            background-color: #e9ecef;
            color: #0d6efd;
        }

        .upload-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #6c757d;
        }

        #dropArea:hover .upload-icon,
        #dropArea.highlight .upload-icon {
            color: #0d6efd;
        }

        /* Error List Styling */
        .error-list {
            background-color: #fee;
            border: 1px solid #fcc;
            border-radius: 4px;
            padding: 10px;
            margin-bottom: 15px;
            text-align: left;
        }

        .error-list ul {
            margin: 5px 0;
            padding-left: 20px;
        }

        .error-list li {
            color: #c00;
        }
    </style>
</head>

<body class="bg-light d-flex flex-column min-vh-100">
    <?php include("./includes/navbar.php"); ?>

    <div class="container flex-grow-1 d-flex align-items-center justify-content-center py-5">
        <div class="col-md-8 col-lg-6">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4 p-md-5 text-center">

                    <h2 class="fw-bold mb-3" data-i18n="index.title">Upload your image</h2>
                    <p class="text-muted mb-4" data-i18n="index.subtitle">Start your mosaic journey by selecting a photo.</p>

                    <?php if (!empty($errors)): ?>
                        <div class="error-list">
                            <ul>
                                <?php foreach ($errors as $error): ?>
                                    <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form action="" method="post" enctype="multipart/form-data">

                        <div id="dropArea">
                            <div class="upload-icon"></div>
                            <h5 class="fw-bold" data-i18n="index.drop_title">Drag & Drop your image here</h5>
                            <p class="text-muted small mb-0" data-i18n="index.drop_hint">or click to browse files</p>
                        </div>

                        <input type="file" id="imageUpload" name="image" accept="image/*" required style="display:none;">

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg" data-i18n="index.button">Upload & Continue</button>
                        </div>

                        <div class="mt-3 text-muted small" data-i18n="index.supported">
                            Supported: JPG, PNG, WEBP, GIF (Max 2MB)
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php include("./includes/footer.php"); ?>

    <script>
        const dropArea = document.getElementById("dropArea");
        const fileInput = document.getElementById("imageUpload");

        // Click to open file dialog
        dropArea.addEventListener("click", () => fileInput.click());

        // Handle file selection via Click
        fileInput.addEventListener("change", () => {
            if (fileInput.files[0]) {
                updateDropArea(fileInput.files[0].name);
            }
        });

        // Handle Drag Events
        ["dragenter", "dragover"].forEach(eventName => {
            dropArea.addEventListener(eventName, e => {
                e.preventDefault();
                dropArea.classList.add("highlight");
            });
        });

        ["dragleave", "drop"].forEach(eventName => {
            dropArea.addEventListener(eventName, e => {
                e.preventDefault();
                dropArea.classList.remove("highlight");
            });
        });

        // Handle Drop
        dropArea.addEventListener("drop", e => {
            const file = e.dataTransfer.files[0];
            if (!file) return;

            fileInput.files = e.dataTransfer.files;
            updateDropArea(file.name);
        });

        // Visual feedback helper
        function updateDropArea(filename) {
            var changeText = (window.I18N && typeof window.I18N.t === 'function') ?
                window.I18N.t('index.change_file', 'Click to change file') :
                'Click to change file';
            dropArea.classList.add('highlight');
            dropArea.innerHTML = `
            <div class="upload-icon text-primary">&uarr;</div>
            <h5 class="fw-bold text-primary">${filename}</h5>
            <p class="text-muted small mb-0">${changeText}</p>
        `;
        }
    </script>
</body>

</html>