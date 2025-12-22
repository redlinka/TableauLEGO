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
    <title>sign in</title>
</head>
<body>
    <h1>sign in</h1>
    <form method="post" enctype="signup">
        <input type="text" name="username" ><br>
		<input type="password" name="password" ><br>
        <input type="submit"><br>
    </form>  
	<?php echo "<a href='connecte.php?img=" . urlencode($id) . "'>already have a account ?</a><br>"; ?>

</html>