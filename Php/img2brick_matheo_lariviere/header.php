<?php
require_once "session.php";
$test = isset($_SESSION["user"]);
?>
<link rel="stylesheet" href="css/header.css">

<header class="site-header">
    <div class="header-left">
        <img src="/img/logo.png" alt="IMG2BRICK logo">
        <a href="index.php">home</a>
    </div>

    <div class="header-center">
        <h1>IMG2BRICK</h1>
    </div>

    <div class="header-right">

        


        <?php if ($test === true): ?>
            <a href="cart.php">cart (<?php  echo all_pan($pdo,$_SESSION["user"]->get_id() )  ?>)</a>
              &nbsp;&nbsp;&nbsp;&nbsp;  &nbsp;&nbsp;&nbsp;&nbsp;  &nbsp;&nbsp;&nbsp;&nbsp;
            <a href="profil.php">Your account</a>
        <?php else: ?>
            
            <a href="log_in.php">Sign in / sign out</a>
        <?php endif; ?>

    </div>
</header>