<?php
session_start();
global $cnx;
include("./config/cnx.php");

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

$stmt = $cnx->prepare("SELECT image_id FROM IMAGE WHERE image_id = ? AND user_id = ? LIMIT 1");
$stmt->execute([$imageId, $userId]);
if (!$stmt->fetchColumn()) {
    header("Location: tiling_selection.php");
    exit;
}

$stmt = $cnx->prepare("SELECT pavage_id FROM TILLING WHERE image_id = ? LIMIT 1");
$stmt->execute([$imageId]);
$pavageId = (int)$stmt->fetchColumn();

if ($pavageId <= 0) {
    header("Location: tiling_selection.php");
    exit;
}

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
    $stmt = $cnx->prepare("
        SELECT address_id
        FROM ADDRESS
        WHERE user_id = ?
        ORDER BY address_id ASC
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $addressId = (int)$stmt->fetchColumn();

    if ($addressId <= 0) {
        $addressId = 1;
    }

    $stmt = $cnx->prepare("INSERT INTO ORDER_BILL (user_id, address_id) VALUES (?, ?)");
    $stmt->execute([$userId, $addressId]);
    $orderId = (int)$cnx->lastInsertId();
}

$stmt = $cnx->prepare("SELECT 1 FROM contain WHERE order_id = ? AND pavage_id = ? LIMIT 1");
$stmt->execute([$orderId, $pavageId]);

if (!$stmt->fetchColumn()) {
    $stmt = $cnx->prepare("INSERT INTO contain (order_id, pavage_id) VALUES (?, ?)");
    $stmt->execute([$orderId, $pavageId]);
}

header("Location: cart.php");
exit;

