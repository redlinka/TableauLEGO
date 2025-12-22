<?php
require_once "session.php";

if(!isset($_SESSION["id_img"])){
    header("Location: index.php");
    exit();
}
header("Location: show_img.php");
exit();
?>