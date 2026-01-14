<?php
    session_start();
    global $cnx;
    include("./config/cnx.php");
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
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Image - TableauLEGO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light d-flex flex-column min-vh-100">
<?php include("./includes/navbar.php"); ?>
</body>

