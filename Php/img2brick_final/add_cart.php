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

try {

    // ORDER_BILL
    $stmt = $cnx->prepare("SELECT order_id FROM ORDER_BILL WHERE user_id = ? AND created_at IS NULL LIMIT 1"); // An order without a date is a "pending shopping cart"
    $stmt->execute([$userId]);
    $orderId = (int)$stmt->fetchColumn();

    if ($orderId <= 0) {
        // Search for his default address
        $stmt = $cnx->prepare("SELECT address_id FROM ADDRESS WHERE user_id = ? AND is_default = 1 LIMIT 1");
        $stmt->execute([$userId]);
        $addressId = (int)$stmt->fetchColumn();

        // Create a temporary empty address if he don't have one
        if ($addressId <= 0) {
            $stmt = $cnx->prepare("INSERT INTO ADDRESS (user_id, is_default) VALUES (?, 0)");
            $stmt->execute([$userId]);
            $addressId = (int)$cnx->lastInsertId();
        }

        $stmt = $cnx->prepare("INSERT INTO ORDER_BILL (user_id, address_id) VALUES (?, ?)");
        $stmt->execute([$userId, $addressId]);
        $orderId = (int)$cnx->lastInsertId();
    }

    // Java processing execution (Stock/Reaction)
    $jarPath = __DIR__ . '/brain.jar';
    $javaCmd = 'java';
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $javaCmd = '"C:\\Program Files\\Eclipse Adoptium\\jdk-25.0.1.8-hotspot\\bin\\java.exe"';
        $exePath      = __DIR__ . '/C_tiler';
    }
    $tilingPath = $_SESSION['pavage_txt'];

    if (file_exists($jarPath) && file_exists($tilingPath)) {
        $cmd = sprintf(
            '%s -cp %s fr.uge.univ_eiffel.ReactionRestock %s %s 2>&1',
            $javaCmd,
            escapeshellarg($jarPath),
            escapeshellarg($tilingPath),
            escapeshellarg($imageId)
        );
        exec($cmd, $output, $returnCode);

        // Select Tiling
        $stmt = $cnx->prepare("SELECT pavage_id, pavage_txt FROM TILLING WHERE image_id = ? LIMIT 1");
        $stmt->execute([$imageId]);
        $tiling = $stmt->fetch(PDO::FETCH_ASSOC);
        $tilingId = (int)$tiling['pavage_id'];

        // Add to cart (contain)
        $stmt = $cnx->prepare("INSERT INTO contain (order_id, pavage_id) VALUES (?, ?)");
        $stmt->execute([$orderId, $tilingId]);
    } else {
        header("Location: tiling_selection.php?error=missing_files");
        exit;
    }

    // Cleaning and Redirection
    unset($_SESSION['step0_image_id'], $_SESSION['step1_image_id'], $_SESSION['step2_image_id'], $_SESSION['step3_image_id'], $_SESSION['step4_image_id']);

    addLog($cnx, "USER", "ADD", "pavage");
    header("Location: cart.php");
    exit;
} catch (PDOException $e) {
    header("Location: index.php?error=db_fail");
    exit;
}
