<?php
session_start();

$errors = [
    'INVALID_TYPE' => 'File type not permitted.',
    'IMAGE_TOO_SMALL' => 'Image size too small (min: 512px × 512px).',
    'FILE_TOO_BIG' => 'File too big (max 2MB).',
    'IMAGE_ALREADY_UPLOADED' => 'Image already uploaded, using cached version.',
    'UPLOAD_FAILED' => 'Upload failed, please try again.'
];

$errorCode = $_GET['error'] ?? null;
$errorMessage = $errors[$errorCode] ?? null;

if (!$errorCode || $errorCode !== 'IMAGE_ALREADY_UPLOADED') {
    $_SESSION['imported'] = false;
    unset($_SESSION['order_obj'], $_SESSION['image_obj'], $_SESSION['image_id']);
}

$showSubmit   = true;
$showContinue = false;

if ($errorCode === 'IMAGE_ALREADY_UPLOADED' && isset($_SESSION['image_id'])) {
    $showSubmit   = false;
    $showContinue = true;
}

if (!empty($_SESSION['imported']) && isset($_SESSION['image_id'])) {
    $showSubmit   = false;
    $showContinue = true;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Img2Brick</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/index.css">
</head>

<body>
    <header class="c-nav">
        <h2>Img2Brick</h2>
        <nav>
            <ul>
                <li><a href="">Home</a></li>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li><a href="my_orders.php">My Orders</a></li>
                <?php endif; ?>
            </ul>
        </nav>
        <div class="right">
            <?php
            if (isset($_SESSION['user_id'])) {
                echo '<a href="account.php" class="profil">
                        <img width="30" height="30" src="https://img.icons8.com/?size=100&id=85147&format=png&color=ffffff" />
                      </a>';
            } else {
                echo '<a href="sign_in.php" class="sign-in">Sign in</a>';
            }
            ?>
        </div>
    </header>

    <div class="container">
        <form action="../src/Service/ImageProcessor.php" method="post" enctype="multipart/form-data">
            <label for="input-file" id="drop-zone">
                <input
                    type="file"
                    name="image"
                    id="input-file"
                    accept=".jpeg, .jpg, .webp, .png"
                    required
                    hidden>
                <div id="img-view">
                    <p>Drag & Drop your image here<br>or click to select</p>
                </div>
            </label><br><br>
            <div class="right">
                <h2>
                    Turn your images
                    <br> into <span>brick paintings.</span>
                </h2>
                <?php if ($errorMessage): ?>
                    <p class="error"><?= htmlspecialchars($errorMessage) ?></p>
                <?php endif; ?>

                <?php if ($showSubmit): ?>
                    <input type="submit" value="Send"></input>
                <?php endif; ?>

                <?php if ($showContinue): ?>
                    <a href=" preview.php?id=<?= $_SESSION['image_id'] ?>" class="continue">Continue</a>
                    <a href="index.php" class="continue cancel">No</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <footer>
        <a href="https://github.com/Draken1003/img2brick" target="_blank">GitHub</a>
    </footer>
</body>
<script src="./assets/js/drag&drop.js"></script>



</html>