<?php
require_once 'connexion.inc.php';
if (isset($_GET['img'])) {
    $id = $_GET['img'];

} else {
    die("Image introuvable !");
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Affichage image</title>
</head>
<body>
    <h1>Voici ton image :</h1>
	<?php
	
	$requete=$pdo->prepare("SELECT image FROM get2brick.img WHERE id_img = :id");
	$requete->execute([':id' => $id]);
	$user = $requete->fetch(PDO::FETCH_ASSOC);
					
	$base64 = base64_encode($user['image']);
	$src = "data:image/jpeg;base64,{$base64}";
	
	echo '<img src="' . htmlspecialchars($src) . '"><br>';
	?>    
	<a href="get_img.php">changer l'image</a><br>
	<?php echo "<a href='connecte.php?img=" . urlencode($id) . "'>validez l'image</a><br>"; ?>
    <form method="post" enctype="multipart/form-data">
        <input type="submit" name="lancer">
    </form>
	

</html>

