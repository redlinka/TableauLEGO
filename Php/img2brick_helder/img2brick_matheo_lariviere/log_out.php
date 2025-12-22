<?php
require_once "session.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sign In</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/log_out.css">
</head>
<body>

<div class="container">
    <h1>Sign In</h1>

    <form method="post" enctype="multipart/form-data">
        <label for="email">Email*</label>
        <input type="email" id="email" name="email" required>

        <label for="password">Password*</label>
        <input type="password" id="password" name="password" required>

        <button type="submit">Sign In</button>
    </form>

    <div class="links">
        <a href="log_in.php">Create an account</a>
        <a href="#">Lost password?</a>
    </div>
</div>
<?php

if (isset($_POST['email'], $_POST['password'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM utilisateur WHERE adresse_mail = :email");
    $stmt->execute([":email" => $email]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (password_verify($password, $row["mdp"])) {
        $user = new user($row["code_uti"], $row["nom"], $row["adresse_mail"], $row["solde"], $row["numero_telephone"]);
        $_SESSION["user"] = $user;
        header("Location: redirection.php");
        exit;
    } else {
        echo "Mot de passe incorrect";
    }

    

    }   

?>
</body>
</html>
