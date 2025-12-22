<?php


/*
* création d'objet PDO de la connexion qui sera représenté par la variable $cnx
*/
$user = "";
$pass = "";
$host = "sqledu.univ-eiffel.fr";
$dbname = "helder.esteves_db";


try {
   $cnx = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
}  
catch (PDOException $e) {
   echo $e;
}


?>