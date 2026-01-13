<?php
session_start();
global $cnx;
include("./config/cnx.php");

addLog($cnx, "USER", "LOG", "out");
// Unset specific variable as requested
unset($_SESSION['id_user']);

// Good practice: destroy the whole session to clear carts/steps too
session_destroy();

header("Location: index.php");
exit;
