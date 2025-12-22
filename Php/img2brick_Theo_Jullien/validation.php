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

try {
    $query = $cnx->prepare("SELECT * FROM picture WHERE filename = :filename");
    $query->bindParam(":filename", $file, PDO::PARAM_STR);
    $query->execute();
    $picture = $query->fetch(PDO::FETCH_ASSOC);

    if ($picture) {
        $extension = $picture["fileextension"];
        $mimeMap = [
                "jpg"  => "image/jpeg",
                "jpeg" => "image/jpeg",
                "png"  => "image/png",
                "webp" => "image/webp"
        ];
        $mimeType = $mimeMap[$extension] ?? "application/octet-stream";
        $base64 = base64_encode($picture["image_data"]);
    } else {
        header("Location: index.php?error=nofile");
        exit;
    }
} catch (PDOException $e) {
    header("Location: index.php?error=db");
    exit();
}
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Validation</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body class="p-validation">
<?php include "header.inc.php"; ?>

<main class="container">
    <h1>Configure your Mosaic</h1>
    <p class="step-info">Step 2: Choose the resolution of your brick picture</p>

    <div class="validation-content">
        <div class="image-preview">
            <img src="data:<?php echo $mimeType; ?>;base64,<?php echo $base64; ?>" alt="Your upload">
        </div>

        <div class="config-panel">
            <form method="post" action="result.php">
                <div class="form-group">
                    <label for="size">Mosaic Resolution</label>
                    <p class="label-desc">A higher resolution means more bricks and more details.</p>
                    <select name="size" id="size" required>
                        <option value="32">32 × 32 (Small - 1024 bricks)</option>
                        <option value="64" selected>64 × 64 (Medium - 4096 bricks)</option>
                        <option value="96">96 × 96 (Large - 9216 bricks)</option>
                    </select>
                </div>

                <button type="submit" class="btn-generate">
                    Generate my mosaic
                </button>
                <a href="index.php" class="btn-back">Change image</a>
            </form>
        </div>
    </div>
</main>

<?php include "footer.inc.php"; ?>
</body>
</html>


