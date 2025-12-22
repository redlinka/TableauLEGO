<?php
require_once '../connexion.inc.php';
require_once '../src/Model/Image.php';

session_start();

if (!isset($_SESSION['image_id'])) {
    header('location: ./index.php');
    exit;
}

$imgId = intval($_SESSION['image_id']);
$sql = "SELECT image_blob FROM images WHERE id = :id";
$stmt = $cnx->prepare($sql);
$stmt->bindParam(':id', $imgId, PDO::PARAM_INT);
$stmt->execute();
$imageBlob = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$imageBlob) {
    die("Image not found");
}

$image = $_SESSION['image_obj'];
$typeMime = htmlspecialchars($image->mime_type);
$imgBase64 = base64_encode($imageBlob['image_blob']);
$width = $image->width;
$height = $image->height;

?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Image Preview - Img2Brick</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="./assets/css/preview.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.js"></script>
</head>

<body>
    <div class="bg-circle"></div>
    <header class="c-nav">
        <h2>Img2Brick</h2>
        <nav>
            <ul>
                <li><a href="index.php">Home</a></li>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li><a href="my_orders.php">My Orders</a></li>
                <?php endif; ?>
            </ul>
        </nav>
        <div class="right">
            <?php
            if (isset($_SESSION['user_id'])) { //if already connected
                echo '<a href="account.php" class="profil">
                        <img width="30" height="30" src="https://img.icons8.com/?size=100&id=85147&format=png&color=ffffff" />
                      </a>';
            } else {
                echo '<a href="sign-in.php" class="sign-in">Sign in</a>';
            }
            ?>
        </div>
    </header>

    <div class="container">

        <!-- Left column: Original image -->
        <div class="c-left">
            <?php if ($width > 2000 || $height > 2000): ?>
                <p class="error" id="errorLabel">
                    Your image is too big, do you want to resize?
                    <button id="resizeButton">Resize</button>
                </p>
            <?php endif; ?>
            <img
                id="image"
                style="<?= ($width > 2000 || $height > 2000) ? 'display:none;' : '' ?>"
                src="data:<?= $typeMime ?>;base64,<?= $imgBase64 ?>"
                alt="Original image">
        </div>

        <!-- Right column: Options and preview -->
        <form id="form" action="../src/Service/UpdateImage.php" method="post" class="c-right">
            <input type="hidden" id="hidden-input" name="cropped_image">
            <div class="c-preview" id="c-preview" name="preview">
                Preview
            </div>
            <div class="c-option">

                <!-- Shape options -->
                <div class="c-tools">
                    <input type="radio" name="shape" id="rectangle" value="rectangle">
                    <label for="rectangle">
                        <img src="https://img.icons8.com/?size=100&id=4UjM7ngqwrzG&format=png&color=ffffff" alt="rectangle">
                    </label>
                    <input type="radio" name="shape" id="square" value="square">
                    <label for="square">
                        <img src="https://img.icons8.com/?size=100&id=s9Ng9tRMMygg&format=png&color=ffffff" alt="square" />
                    </label>

                    <button type="button" id="btn-crop">Apply</button>
                </div>

                <!-- Size options -->
                <div class="c-size-option">
                    <select name="size" id="size" required>
                        <option value="" selected hidden>Choose a size</option>
                        <option value="small">32x32</option>
                        <option value="medium">64x64</option>
                        <option value="large">96x96</option>
                    </select>
                </div>
                <input type="submit" value="Generate my mosaic">
            </div>
        </form>
    </div>
    <footer>
        <a href="https://github.com/Draken1003/img2brick" target="_blank">GitHub</a>
    </footer>

    <script src="assets/js/preview.js"></script>

</body>

</html>