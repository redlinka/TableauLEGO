<?php
session_start();
global $cnx;
include("./config/cnx.php");

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


if (!isset($_SESSION['userId'])) {
    header("Location: connexion.php");
    exit;
}

if (!isset($_SESSION['step4_image_id'])) {
    header("Location: tiling_selection.php");
    exit;
}

$userId  = (int)$_SESSION['userId'];
$imageId = (int)$_SESSION['step4_image_id'];

/* 1) Vérifier que l'image appartient au user */
$stmt = $cnx->prepare("SELECT image_id FROM IMAGE WHERE image_id = ? AND user_id = ? LIMIT 1");
$stmt->execute([$imageId, $userId]);
if (!$stmt->fetchColumn()) {
    header("Location: tiling_selection.php");
    exit;
}

/* 2) Trouver le pavage_id associé à l'image (dans TILLING) */
$stmt = $cnx->prepare("SELECT pavage_id FROM TILLING WHERE image_id = ? LIMIT 1");
$stmt->execute([$imageId]);
$pavageId = (int)$stmt->fetchColumn();

if ($pavageId <= 0) {
    header("Location: tiling_selection.php");
    exit;
}

/* 3) Récupérer ou créer le panier */
$stmt = $cnx->prepare("
    SELECT order_id
    FROM ORDER_BILL
    WHERE user_id = ?
      AND created_at IS NULL
    LIMIT 1
");
$stmt->execute([$userId]);
$orderId = (int)$stmt->fetchColumn();

if ($orderId <= 0) {
    $stmt = $cnx->prepare("INSERT INTO ORDER_BILL (user_id) VALUES (?)");
    $stmt->execute([$userId]);
    $orderId = (int)$cnx->lastInsertId();
}

/* 4) Éviter les doublons (optionnel mais recommandé) */
$stmt = $cnx->prepare("SELECT 1 FROM contain WHERE order_id = ? AND pavage_id = ? LIMIT 1");
$stmt->execute([$orderId, $pavageId]);

if (!$stmt->fetchColumn()) {
    $stmt = $cnx->prepare("INSERT INTO contain (order_id, pavage_id) VALUES (?, ?)");
    $stmt->execute([$orderId, $pavageId]);
}

/* 5) Rediriger vers le panier */
header("Location: cart.php");
exit;