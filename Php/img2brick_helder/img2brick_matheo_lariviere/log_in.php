<?php
require_once "session.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/log_in.css">
</head>
<body>

<div class="container">
    <h1>Create Account</h1>

    <form  method="post" enctype="multipart/form-data">
        <label for="email">Email*</label>
        <input type="email" id="email" name="email" required>

        <label for="password">Password*</label>
        <input type="password" id="password" name="password" required>

        <label for="name">Name*</label>
        <input type="text" id="name" name="name" required>

        <label for="phone">Phone number</label>
        <input type="tel" id="phone" name="phone">

        <button type="submit">Create Account</button>
    </form>

    <div class="links">
        <a href="log_out.php">Already have an account?</a>
        <a href="#">Lost password?</a>
    </div>
</div>

<?php

if (isset($_POST['email'], $_POST['password'], $_POST['name'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $name = $_POST['name'];
    $phone = $_POST['phone'] ?? null;
    $solde = 0;

    $stmt = $pdo->prepare("SELECT * FROM utilisateur WHERE adresse_mail = :email");
    $stmt->execute([":email" => $email]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (passworld_correct($password) && !$row){
        $sql = "INSERT INTO get2brick.utilisateur (nom,adresse_mail,mdp,solde,numero_telephone) VALUES (:id_uti,:mail,:mdp,:solde,:num)";
		$stmt = $pdo->prepare($sql);
		$stmt->bindParam(':id_uti', $name);
		$stmt->bindParam(':mail', $email);
        $stmt->bindParam(':mdp', $hash);
        $stmt->bindParam(':solde', $solde);
		$stmt->bindParam(':num', $phone);
		$stmt->execute();
		$lastId = $pdo->lastInsertId();
        header("Location: log_out.php");
        exit;

    }else{
        echo "email deja utiliser ou mot de passe non conforme (12 charcter-majuscule+minuscule+chiffre+charchter speciaux)";
    }
}

?>

</body>
</html>
