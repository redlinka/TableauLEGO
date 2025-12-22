<?php

require_once '../../connexion.inc.php';
require_once '../Model/Image.php';

session_start();

$cnx->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if (!isset($_POST['cropped_image'])) {
    header("Location: ../public/index.php");
    exit;
}

$image_base64 = $_POST['cropped_image'];
$_SESSION['size_choose'] = $_POST['size'];

$image_base64 = substr($image_base64, strpos($image_base64, ',') + 1);
$image_data = base64_decode($image_base64);


if (!isset($_SESSION['image_obj'])) {
    header("Location: ../../public/index.php");
    exit;
} else {

    $image = $_SESSION['image_obj'];

    //update image object informations
    $image->width = getimagesizefromstring($image_data)[0];
    $image->height = getimagesizefromstring($image_data)[1];
    $image->size = strlen($image_data);
    $image_blob = $image_data;
    $image->img_hash = hash('sha256', $image_blob);

    $cnx->beginTransaction();

    try {
        $sql = "UPDATE images SET 
                width = :width, 
                height = :height, 
                mime_type = :mime_type, 
                size_bytes = :size_bytes, 
                image_blob = :img_blob, 
                img_hash = :img_hash
                WHERE id = :image_id
            ";
        $stmt = $cnx->prepare($sql);

        $stmt->bindParam(':width', $image->width, PDO::PARAM_INT);
        $stmt->bindParam(':height', $image->height, PDO::PARAM_INT);
        $stmt->bindParam(':mime_type', $image->mime_type, PDO::PARAM_STR);
        $stmt->bindParam(':size_bytes', $image->size, PDO::PARAM_INT);
        $stmt->bindParam(':img_blob', $image_blob, PDO::PARAM_LOB);
        $stmt->bindParam(':img_hash', $image->img_hash, PDO::PARAM_STR);
        $stmt->bindParam(':image_id', $image->img_id, PDO::PARAM_INT);

        $stmt->execute();

        $cnx->commit();

        header("Location: ../../public/generate.php");
        exit;
    } catch (Exception $e) {
        if ($cnx->inTransaction()) {
            $cnx->rollBack();
        }
        header("Location: ../../public/preview.php");
    }
}
