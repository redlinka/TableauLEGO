<script src="https://unpkg.com/friendly-challenge@0.9.12/widget.min.js" async defer></script>
<?php
require_once "session.php";

$rows = get_image($pdo, 47);

echo $rows->get_id();
echo '<div class="img-container">';
echo '<img src="' . htmlspecialchars($rows->get_img()) . '" alt="Image">';
echo '</div>';

$mdp = "password";
$hash = password_hash($mdp, PASSWORD_DEFAULT);

if (password_verify("password", $hash)) {
    echo "Mot de passe correct";
} else {
    echo "Mot de passe incorrect";
}
if (isset($_SESSION["user"])){
    echo $_SESSION["user"]->get_id();
}

if (passworld_correct("1234")){
    echo "aaaaaaaaaaaaaaa";
}



?>
