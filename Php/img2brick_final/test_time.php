<?php
global $cnx;
include("./config/cnx.php");
echo "Heure PHP : " . date('Y-m-d H:i:s') . "<br>";

$res = $cnx->query("SELECT NOW() as db_time")->fetch();
echo "Heure MySQL : " . $res['db_time'];