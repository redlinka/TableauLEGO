<?php
require_once "session.php";

if (isset($_SESSION["id_img"])) {
    $id = (int) $_SESSION["id_img"];
} else {
    header("Location: index.php");
    exit();
}

$requete = $pdo->prepare("SELECT image FROM img WHERE id_img = :id");
$requete->execute([':id' => $id]);
$user = $requete->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("Image introuvable !");
}

$base64 = base64_encode($user['image']);


$src = "data:image/jpeg;base64,{$base64}";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Affichage image</title>
    <link rel="stylesheet" href="css/show_img.css">
</head>
<body>



<main class="page">
    <h1 class="title">Voici ton image :</h1>

    <section class="preview-row">
        <div class="img-box">
            <img src="<?= htmlspecialchars($src) ?>" alt="Image">
        </div>
        <div class="white-square" aria-hidden="true"></div>
    </section>

    <section class="actions">
        <a class="btn btn-secondary" href="index.php">Changer</a>
        <a class="btn btn-primary" href="choice.php?img=<?= urlencode($id) ?>">Soumettre</a>
    </section>
</main>
</body>
</html>


