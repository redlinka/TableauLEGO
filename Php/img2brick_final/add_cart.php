<?php
session_start();
global $cnx;
include("./config/cnx.php");

function debug_stop($msg) {
    echo "<pre style='background:#111;color:#0f0;padding:12px;border-radius:8px;'>";
    echo htmlspecialchars($msg) . "\n\n";
    echo "SESSION:\n";
    print_r($_SESSION);
    echo "</pre>";
    exit;
}

if (!isset($_SESSION['userId'])) {
    debug_stop("STOP: userId absent (pas connecté)");
}

if (!isset($_SESSION['step4_image_id'])) {
    debug_stop("STOP: step4_image_id absent (tu as bien généré l'image LEGO avant de finaliser ?)");
}

$userId  = (int)$_SESSION['userId'];
$imageId = (int)$_SESSION['step4_image_id'];

/* 1) Vérifier que l'image appartient au user */
$stmt = $cnx->prepare("SELECT image_id, user_id, path FROM IMAGE WHERE image_id = ? LIMIT 1");
$stmt->execute([$imageId]);
$imgRow = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$imgRow) {
    debug_stop("STOP: IMAGE id=$imageId introuvable");
}
if ((int)$imgRow['user_id'] !== $userId) {
    debug_stop("STOP: IMAGE n'appartient pas au user. image.user_id={$imgRow['user_id']} userId=$userId");
}

/* 2) Trouver le pavage_id associé */
$stmt = $cnx->prepare("SELECT pavage_id FROM TILLING WHERE image_id = ? LIMIT 1");
$stmt->execute([$imageId]);
$pavageId = (int)$stmt->fetchColumn();

if ($pavageId <= 0) {
    debug_stop("STOP: Aucun TILLING pour image_id=$imageId. Donc impossible d'ajouter au panier.");
}

debug_stop("OK: Tout est bon. pavage_id=$pavageId (tu peux enlever debug et faire l'insert panier)");
