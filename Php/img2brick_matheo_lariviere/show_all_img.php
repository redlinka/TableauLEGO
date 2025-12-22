<?php 
require_once 'connexion.inc.php';

/* ---- SUPPRESSION ---- */
if (isset($_POST['delete_id'])) {
    $id = (int) $_POST['delete_id'];

    $del = $pdo->prepare("DELETE FROM get2brick.img WHERE id_img = :id");
    $del->execute([':id' => $id]);

    
    header("Location: ".$_SERVER['PHP_SELF']);
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Affichage de toutes les images</title>
    <link rel="stylesheet" href="show_all_img.css">
</head>
<body>

<h1>Voici toutes les images :</h1>

<div class="gallery">
<?php
$requete = $pdo->prepare("SELECT id_img, image FROM get2brick.img");
$requete->execute();
$rows = $requete->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as $row) {
    $base64 = base64_encode($row['image']);
    $src = "data:image/jpeg;base64,{$base64}";
    ?>
    
    <div class="img-container">
        <img src="<?= htmlspecialchars($src) ?>" alt="Image">

        <form method="post" class="delete-form">
            <input type="hidden" name="delete_id" value="<?= $row['id_img'] ?>">
            <button type="submit" class="delete-btn">Supprimer</button>
        </form>
    </div>

<?php } ?>
</div>

</body>
</html>
