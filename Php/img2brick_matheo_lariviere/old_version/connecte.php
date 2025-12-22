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
    <title>sign up</title>
</head>
<body>
    <h1>sign up</h1>
    <form method="post" enctype="signup">
        <input type="text" name="username" ><br>
		<input type="password" name="password" ><br>
        <input type="submit"><br>
    </form>  
	<?php echo "<a href='inscrit.php?img=" . urlencode($id) . "'>no account ?</a><br>"; ?>

</html>