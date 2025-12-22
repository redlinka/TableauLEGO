<?php
require_once "session.php";

if (!isset($_POST['filter'], $_SESSION['id_img'])) {
    header("Location: choose_filter.php");
    exit;
}

$filter = $_POST['filter'];
$id_img = (int)$_SESSION['id_img'];

switch ($filter) {
    case 'original':
        $newPrice = 1;
        break;

    case 'bw':
        $newPrice = 5;
        break;

    case 'vivid':
        $newPrice = 3;
        break;

    default:
        header("Location: choice.php");
        exit;
}

$stmt = $pdo->prepare("UPDATE img SET prix = :prix WHERE id_img = :id");
$stmt->execute([':prix' => $newPrice,':id' => $id_img]);


$nb = 1;
$id_uti = $_SESSION['user']->get_id();
$id_img = $_SESSION['id_img'];


$sql = "SELECT nb FROM panier WHERE code_uti = :id_uti AND id_img = :id_img";
$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':id_uti' => $id_uti,
    ':id_img' => $id_img
]);

$row = $stmt->fetch(PDO::FETCH_ASSOC);

if ($row) {
    $sql = "UPDATE panier
            SET nb = nb + 1
            WHERE code_uti = :id_uti AND id_img = :id_img";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':id_uti' => $id_uti,
        ':id_img' => $id_img
    ]);
} else {
    $sql = "INSERT INTO panier (code_uti, id_img, nb)
            VALUES (:id_uti, :id_img, :nb)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':id_uti' => $id_uti,
        ':id_img' => $id_img,
        ':nb'     => $nb
    ]);
}



header("Location: end.php");
exit;
?>

