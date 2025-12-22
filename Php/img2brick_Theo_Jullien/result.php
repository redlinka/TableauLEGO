<?php
/* DUPLICATED CODE */
session_start();
require_once "connection.inc.php";
$cnx = getConnection();

if (!isset($_SESSION["allowed_file"]) || !isset($_SESSION["allowed_file_expire"])) {
    header("Location: index.php?error=nofile");
    exit;
}

if (time() > $_SESSION["allowed_file_expire"]) {
    unset($_SESSION["allowed_file"]);
    unset($_SESSION["allowed_file_expire"]);
    header("Location: index.php?error=expired");
    exit;
}

$file = $_SESSION["allowed_file"];
/* DUPLICATED CODE END*/

if (isset($_POST["size"])) {
    $_SESSION["order_size"] = intval($_POST["size"]);
}

$size = $_SESSION["order_size"] ?? null;
if (!$size) {
    header("Location: validation.php?error=nosize");
    exit;
}

try {
    // Start of the protected database block
    $query = $cnx->prepare("SELECT * FROM picture WHERE filename = :filename");
    $query->bindParam(":filename", $file, PDO::PARAM_STR);
    $query->execute();
    $picture = $query->fetch(PDO::FETCH_ASSOC);

    if (!$picture) {
        header("Location: index.php?error=nofile");
        exit;
    }
} catch (PDOException $e) {
    // Handle database errors (log them or redirect)
    // error_log($e->getMessage()); // Useful for production logs
    header("Location: index.php?error=db");
    exit;
}

/*
$pictureId = $picture["id"];
$method = $_POST["method"];

$destinationFile = "/var/www/tmp/pixelized_" . uniqid() . "." . $extension;
$jarPath = "/var/www/java/pixelizer.jar";


$cmd = "java -jar " . escapeshellarg($jarPath) . " " .
        escapeshellarg($pictureId) . " " .
        escapeshellarg($size . 'x' . $size) . " " .
        escapeshellarg($method);
exec($cmd, $output, $returnCode);

if ($returnCode !== 0) {
    die("Erreur Java");
}

$pixelizedData = file_get_contents($destinationFile);
$pixelizedBase64 = base64_encode($pixelizedData);
*/

$extension = $picture["fileextension"];
$mimeMap = [
    "jpg"  => "image/jpeg",
    "jpeg" => "image/jpeg",
    "png"  => "image/png",
    "webp" => "image/webp"
];
$mimeType = $mimeMap[$extension] ?? "application/octet-stream";
$base64 = base64_encode($picture["image_data"]);

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Result</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="p-result">
<?php include "header.inc.php"; ?>

<main class="container">
    <header class="page-title">
        <h1>Your Brick Mosaics</h1>
        <p>Select the color palette that best fits your style.</p>
        <div class="badge-size">Size: <?php echo $size; ?> × <?php echo $size; ?></div>
    </header>

    <form method="POST" action="order.php">
        <input type="hidden" name="picture_id" value="<?php echo $picture['id']; ?>">
        <input type="hidden" name="size" value="<?php echo $size; ?>">

        <div class="gallery">
            <label class="item-card" for="choice-blue">
                <div class="img-container">
                    <img class="filter-blue" src="data:<?php echo $mimeType; ?>;base64,<?php echo $base64; ?>" alt="Blue rendering">
                </div>
                <div class="card-content">
                    <input type="radio" name="choice" value="blue" id="choice-blue" required>
                    <span class="custom-radio"></span>
                    <span class="variant-name">Ocean Blue</span>
                    <div class="details">
                        <p>12 Unique Colors</p>
                        <p class="price"><?php echo number_format($size * 0.15, 2); ?> €</p>
                    </div>
                </div>
            </label>

            <label class="item-card" for="choice-red">
                <div class="img-container">
                    <img class="filter-red" src="data:<?php echo $mimeType; ?>;base64,<?php echo $base64; ?>" alt="Red rendering">
                </div>
                <div class="card-content">
                    <input type="radio" name="choice" value="red" id="choice-red">
                    <span class="custom-radio"></span>
                    <span class="variant-name">Classic Red</span>
                    <div class="details">
                        <p>8 Unique Colors</p>
                        <p class="price"><?php echo number_format($size * 0.12, 2); ?> €</p>
                    </div>
                </div>
            </label>

            <label class="item-card" for="choice-bw">
                <div class="img-container">
                    <img class="filter-bw" src="data:<?php echo $mimeType; ?>;base64,<?php echo $base64; ?>" alt="B&W rendering">
                </div>
                <div class="card-content">
                    <input type="radio" name="choice" value="bw" id="choice-bw">
                    <span class="custom-radio"></span>
                    <span class="variant-name">Essential Noir</span>
                    <div class="details">
                        <p>2 Unique Colors</p>
                        <p class="price"><?php echo number_format($size * 0.10, 2); ?> €</p>
                    </div>
                </div>
            </label>
        </div>

        <div class="actions">
            <button type="submit" class="btn-confirm">Continue</button>
            <a href="validation.php" class="btn-secondary">Change resolution</a>
        </div>
    </form>
</main>

<?php include "footer.inc.php"; ?>
</body>
</html>
