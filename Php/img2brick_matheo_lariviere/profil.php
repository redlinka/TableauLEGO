<?php
require_once "session.php";

if (isset($_SESSION["user"])){
    $user = $_SESSION["user"];
}else{
    header("Location: log_out.php");
    exit;
}


$stmt = $pdo->prepare("SELECT * FROM favoris WHERE code_uti = :id LIMIT 3");
$stmt->execute([":id" => $user->get_id()]);
$row_com = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT * FROM panier WHERE code_uti = :id LIMIT 3");
$stmt->execute([":id" => $user->get_id()]);
$row_pan = $stmt->fetchAll(PDO::FETCH_ASSOC);
      
$stmt = $pdo->prepare("SELECT * FROM facture WHERE code_uti = :id LIMIT 3");
$stmt->execute([":id" => $user->get_id()]);
$row_fac = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>



<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Affichage image</title>
    <link rel="stylesheet" href="css/profil.css">
</head>
<body>

<main class="page">
</br>
</br>
</br>
  <h1 class="title">Bienvenue <?= htmlspecialchars($user->get_name()) ?></h1>

  <p class="subtitle">Souhaitez-vous changer les paramètres de votre compte ?</p>

  <form class="form-grid" method="post" enctype="multipart/form-data">
    <div class="field">
      <label for="email">Email*</label>
      <input type="email" id="email" name="email" required>
    </div>

    <div class="field">
      <label for="old_password">Ancien mot de passe*</label>
      <input type="password" id="old_password" name="old_password" required>
    </div>

    <div class="field">
      <label for="new_password">Nouveau mot de passe*</label>
      <input type="password" id="new_password" name="new_password" required>
    </div>

    <div class="field">
      <label for="name">Nom*</label>
      <input type="text" id="name" name="name" required>
    </div>

    <div class="field">
      <label for="phone">Téléphone</label>
      <input type="tel" id="phone" name="phone">
    </div>

    <div class="field field-btn">
      <button type="submit">Modifier le compte</button>
    </div>
  </form>


  <section class="gallery-section" data-gallery>
    <h2>Vos commandes</h2>

    <div class="gallery-row">


<?php foreach ($row_fac as $row): ?>
  <?php
    $imgObj = get_image($pdo, (int)$row['id_img']);
    if (!$imgObj) continue;

    $src = $imgObj->get_img();

    $dateArrivee = $row['date_arrivee'] ?? ($row['date_arrivée'] ?? null);
    $aujourdhui  = date('Y-m-d');

    if ($dateArrivee !== null && $dateArrivee <= $aujourdhui) {
        $statut = "Livrée";
        $classStatut = "status-delivered";
    } else {
        $statut = "En cours de livraison";
        $classStatut = "status-progress";
    }            

    if ($dateArrivee <= $aujourdhui) {
        $statut = "Livrée";
        $classStatut = "status-delivered";
    } else {
        $statut = "En cours de livraison";
        $classStatut = "status-progress";
    }
  ?>
  <div class="img-container">
    <img src="<?= $src ?>" alt="Image">

    <span class="order-status <?= $classStatut ?>">
      <?= $statut ?>
    </span>
  </div>
<?php endforeach; ?>







    </div>

    <a href="see_order.php" class="more-btn">Afficher plus</a>
  </section>

  <section class="gallery-section" data-gallery>
    <h2>Votre panier</h2>
    <div class="gallery-row">

<?php foreach ($row_pan as $row): ?>
  <?php
    $imgObj = get_image($pdo, (int)$row['id_img']);
    if (!$imgObj) continue;

    $src = $imgObj->get_img(); 
  ?>
  <div class="img-container">
    <img src="<?= $src ?>" alt="Image">

    <form method="post" class="delete-form">
      <input type="hidden" name="delete_id" value="<?= (int)$row['id_img'] ?>">
      <button type="submit" class="delete-btn">Supprimer</button>
    </form>
  </div>
<?php endforeach; ?>

    </div>
    <a href="cart.php" class="more-btn">Afficher plus</a>
  </section>


<!--  <section class="gallery-section" data-gallery>
    <h2>Vos favoris</h2>
    <div class="gallery-row">
      
<?php foreach ($row_com as $row): ?>
  <?php
    $imgObj = get_image($pdo, (int)$row['id_img']);
    if (!$imgObj) continue;

    $src = $imgObj->get_img(); 
  ?>
  <div class="img-container">
    <img src="<?= $src ?>" alt="Image"> 

    <form method="post" class="delete-form">
      <input type="hidden" name="delete_id" value="<?= (int)$row['id_img'] ?>">
      <button type="submit" class="delete-btn">Supprimer</button>
    </form>
  </div>
<?php endforeach; ?> 

    </div>
    <a href="page2.php" class="more-btn">Afficher plus</a>
  </section>-->

  <div class="actions-row">
  <a href="redirection.php" class="more-btn">get back</a>
  <a href="deco.php" class="logout-btn">Se déconnecter</a>
  </div>
    </div>

</main>
