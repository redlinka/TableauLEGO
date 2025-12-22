<?php

$user =  "";
$pass =  "";
try {
   
    $pdo = new PDO('pgsql:host=localhost;port=5432;dbname=postgres', $user, $pass); 
    $pdo->query("SET search_path TO cours1");
}
catch (PDOException $e) {
    echo "ERREUR : La connexion a échouée<br>";

}

?>