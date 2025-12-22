<?php
require_once "session.php";

if (!isset($_SESSION["user"])) {
    header("Location: log_out.php");
    exit;
}

if (!isset($_SESSION["id_img"])) {
    // si pas d'image en session, renvoie où tu veux
    header("Location: profil.php");
    exit;
}

$id_img = (int)$_SESSION["id_img"];
$imgObj = get_image($pdo, $id_img);

if (!$imgObj) {
    header("Location: profil.php");
    exit;
}

$src = $imgObj->get_img(); 
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>Choisir un filtre</title>
  <link rel="stylesheet" href="css/choice.css">
</head>
<body>

<main class="page">
  <h1>Choisis un filtre</h1>
  <p class="subtitle">Clique sur une version pour la sélectionner.</p>

  <form method="post" action="choice_filter.php" class="grid">

    <button class="card" type="submit" name="filter" value="original">
      <div class="thumb">
        <img src="<?= $src ?>" alt="Original" class="f-original">
      </div>
      <div class="label">Original (2)</div>
    </button>


    <button class="card" type="submit" name="filter" value="bw">
      <div class="thumb">
        <img src="<?= $src ?>" alt="Noir &amp; blanc" class="f-bw">
      </div>
      <div class="label">black &amp; white (5)</div>
    </button>


    <button class="card" type="submit" name="filter" value="vivid">
      <div class="thumb">
        <img src="<?= $src ?>" alt="Vif" class="f-vivid">
      </div>
      <div class="label">invert (3)</div>
    </button>
  </form>

  <div class="actions">
    <a href="show_img.php" class="back" href="profil.php">Retour</a>
  </div>
</main>

</body>
</html>
