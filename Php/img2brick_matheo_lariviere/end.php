<?php
require_once "session.php";
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Navigation</title>
  <link rel="stylesheet" href="css/end.css">
</head>
<body>

  <div class="buttons-container">
    <a href="index.php" class="nav-btn"> Back to start </a>

    <a href="cart.php" class="nav-btn secondary"> Go to cart (<?php  echo all_pan($pdo,$_SESSION["user"]->get_id() )  ?>) </a>
  </div>

</body>
</html>
