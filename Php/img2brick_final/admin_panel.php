<?php
global $cnx;
require_once __DIR__ . '/i18n.php';
if (!isset($_SESSION['userId']) || !isset($_SESSION['username'])) {
    header("Location: connexion.php");
    exit;
}
$navUsername = $_SESSION['username'];
if ($navUsername != '4DM1N1STRAT0R_4ND_4LM16HTY') {
    header("Location: index.php");
    exit;
}

