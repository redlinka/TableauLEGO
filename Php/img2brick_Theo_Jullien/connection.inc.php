<?php
function getConnection()
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . "/config.php";
    }

    try {
        $cnx = new PDO("mysql:host=sqledu.univ-eiffel.fr;dbname=theo.jullien_db;charset=utf8", $config["DB_USER"], $config["DB_PASS"]);
        $cnx->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $cnx;
    } catch (PDOException $e) {
        //die("Error : " . $e->getMessage()); // remove error message in prod
        die("A database error occurred. Please try again later.");
    }
}