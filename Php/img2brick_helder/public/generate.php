<?php
require_once '../connexion.inc.php';
require_once '../src/Model/Order.php';

session_start();

if (!isset($_SESSION['image_id'])) {
    header('location: ./index.php');
    exit;
}

$imgId = intval($_SESSION['image_id']);
$sql = "SELECT filename, mime_type, image_blob FROM images WHERE id = :id";
$stmt = $cnx->prepare($sql);
$stmt->bindParam(':id', $imgId, PDO::PARAM_INT);
$stmt->execute();
$image = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$image) {
    header('location: ./index.php');
    exit;
}

$typeMime = htmlspecialchars($image['mime_type']);
$imgBase64 = base64_encode($image['image_blob']);


$sizeMap = [
    'small' => '32x32',
    'medium' => '64x64',
    'large' => '96x96',
];

$size = isset($_SESSION['size_choose']) && isset($sizeMap[$_SESSION['size_choose']])
    ? $sizeMap[$_SESSION['size_choose']] : null;

if (isset($_SESSION['order_obj'])) {
    $_SESSION['order_obj']->size = $size;
    $price = match ($size) {
        '32x32' => 1000,
        '64x64' => 4000,
        '96x96' => 9000,
        default => 0,
    };
    $_SESSION['order_obj']->price_cents = $price;
    $price = number_format($price / 100, 2) . ' €';
}

?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Generated Mosaics - Img2Brick</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="./assets/css/generate.css">
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
                echo '<a href="account.php" class="profil"><img width="30" height="30" src="https://img.icons8.com/?size=100&id=85147&format=png&color=ffffff" /></a>';
            } else {
                echo '<a href="sign-in.php" class="sign-in">Sign in</a>';
            }
            ?>
        </div>
    </header>

    <div class="container">
        <h1>Here are your generated mosaics</h1>
        <form action="./order.php" method="post" class="image-container">
            <div class="mosaics">
                <?php
                $mosaicVariants = [
                    'blue' => 'filter: hue-rotate(180deg) saturate(200%);',
                    'red' => 'filter: hue-rotate(-50deg) saturate(200%);',
                    'bw' => 'filter: grayscale(1);',
                ];

                foreach ($mosaicVariants as $id => $style): ?>
                    <div class="mosaic-option">
                        <label class="mosaic-view" for="<?= $id ?>">
                            <img src="data:<?= $typeMime ?>;base64,<?= $imgBase64 ?>"
                                style="<?= $style ?>"
                                alt="<?= ucfirst($id) ?> mosaic">
                            <div class="mosaic-info">
                                <p>Size <span><?= $size ?></span></p>
                                <p>Number of colors <span>46</span></p>
                            </div>
                            <span><?= $price ?></span>
                        </label>
                        <input type="radio" name="mosaic" id="<?= $id ?>" value="<?= $id ?>" hidden>
                    </div>
                <?php endforeach; ?>
            </div>

            <input type="submit" value="Confirm my choice">
        </form>
    </div>
    <footer>
        <a href="https://github.com/Draken1003/img2brick" target="_blank">GitHub</a>
    </footer>

    <script>
        const mosaicRadios = document.querySelectorAll('input[name="mosaic"]');
        const form = document.querySelector('form');

        form.addEventListener('submit', (e) => {
            const checked = document.querySelector('input[name="mosaic"]:checked');
            if (!checked) {
                e.preventDefault();
                alert("Please select a mosaic!");
            }
        });

        mosaicRadios.forEach(radio => {
            radio.addEventListener('change', () => {
                mosaicRadios.forEach(r => r.closest('.mosaic-option').classList.remove('selected'));
                radio.closest('.mosaic-option').classList.add('selected');
            });
        });
    </script>

</body>

</html>