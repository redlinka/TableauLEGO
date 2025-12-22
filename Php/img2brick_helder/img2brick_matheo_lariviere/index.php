<?php
/*-------------------------parametre-----------------------------*/
require_once "session.php";
$lenght = 2000000;
$autorize = array('image/jpeg','image/jpg','image/png','image/webp');
$largeur_accepter = 112; 
$hauteur_accepter = 112;
$mod = 1;
$src = "";
/*---------------------------------------------------------------*/
?>



<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Envoi de photo par formulaire</title>
	<link rel="stylesheet" href="css/index.css">
</head>
<body>


   <main class="page">
    <h1 class="page-title">Choose a file to turn it into LEGO bricks</h1>
</br>

    <section class="card">
        <form class="upload-form" method="post" enctype="multipart/form-data">
            <input class="file-input" type="file" name="photo" id="photoInput" accept="image/*">
            <input class="submit-btn" type="submit" value="Submit">
        </form>





	




    <?php

    
    if (isset($_FILES['photo'])) {
        $dossier = "uploads/";
        if (!is_dir($dossier)) {
            mkdir($dossier, 0777, true); 
        }
        
	$infos_image = @getImageSize($_FILES['photo']['tmp_name']);
	$largeur = $infos_image[0]; 
   	$hauteur = $infos_image[1];
	
	if (!in_array($_FILES['photo']['type'], $autorize)){
		echo "image non conforme (.jpg, .jpeg, .png, .webp)";
	}else{
		if ($_FILES['photo']['size'] >= $lenght){
			echo "image trop lourde (maximum 2 MO)";
		}else{
		
		
			if ($largeur < $largeur_accepter || $hauteur < $hauteur_accepter){
  				echo "image trop petit (minimum 112 pixel par 112 pixel)";
  			}else{
				
				$image_blob = file_get_contents($_FILES['photo']['tmp_name']);
    			if (isset($_FILES['photo'])) {
					$date_t = date("y.m.j");
    				$sql = "INSERT INTO get2brick.img (image,date) VALUES (:id_uti,:date_today)";
					$stmt = $pdo->prepare($sql);
					$stmt->bindParam(':id_uti', $image_blob, PDO::PARAM_LOB);
					$stmt->bindParam(':date_today', $date_t);
					$stmt->execute();
					$lastId = $pdo->lastInsertId();
					/*echo "Image insérée avec id_img = " . $lastId . "<br>";*/
					
					$requete=$pdo->prepare("SELECT image FROM get2brick.img WHERE id_img = :id");
					$requete->execute([':id' => $lastId]);
					$user = $requete->fetch(PDO::FETCH_ASSOC);
					
					$base64 = base64_encode($user['image']);
					$src = "data:image/png;base64,{$base64}";
					
					$mod = 2;
					
					$_SESSION["id_img"] = $lastId;

					echo '<img src="' . htmlspecialchars($src) . '"><br>';
					echo "<a href='show_img.php'>Voir l’image sur une autre page</a>";
 


				}
			
    		}
     	} 
    }
    }
    ?>
	
	
</body>
</html>

<!-- $requete=$cnx->prepare("SELECT * FROM projet_tran.utilisateur WHERE adresseMail = :mail AND motDePasse = :mdp");
        $requete->execute([':mail' => $mail, ':mdp' => $mdp]);
        $user = $requete->fetch(PDO::FETCH_ASSOC); -->
