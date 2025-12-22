<?php
require_once  '../../connexion.inc.php';
require_once  '../Model/Image.php';
require_once  '../Model/Order.php';

session_start();

function redirectWithError(string $code)
{
    header("Location: ../../public/index.php?error={$code}");
    exit;
}

function imgAlreadyInDataBase($hash, $id)
{
    global $cnx;
    $sql = "SELECT COUNT(*) FROM images WHERE img_hash = :hash AND user_id = :user_id";
    $stmt = $cnx->prepare($sql);
    $stmt->bindParam(':hash', $hash, PDO::PARAM_STR);
    $stmt->bindParam(':user_id', $id, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchColumn() > 0;
}

function getImgIdInDatabase($hash, $id)
{
    global $cnx;
    $sql = "SELECT id FROM images WHERE img_hash = :hash AND user_id = :user_id";
    $stmt = $cnx->prepare($sql);
    $stmt->bindParam(':hash', $hash, PDO::PARAM_STR);
    $stmt->bindParam(':user_id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['id'] : null;
}

// Check if a file was uploaded without errors
if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    redirectWithError('UPLOAD_FAILED');
}

$userId = $_SESSION['user_id'] ?? null;
$tmpPath = $_FILES['image']['tmp_name'];

$imgInfo = getimagesize($tmpPath);
if ($imgInfo === false) {
    redirectWithError('INVALID_TYPE');
}

[$width, $height] = $imgInfo;
$mimeType = $imgInfo['mime'] ?? '';

$allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'];

// File type verification
if (!in_array($mimeType, $allowedMimeTypes, true)) {
    redirectWithError('INVALID_TYPE');
}

// Verification of minimum size
if ($width < 512 || $height < 512) {
    redirectWithError('IMAGE_TOO_SMALL');
}

$maxSize = 2 * 1024 * 1024; // 2MB
$fileSize = (int) $_FILES['image']['size'];

// Verification of maximum size (2 MB)
if ($fileSize > $maxSize) {
    redirectWithError('FILE_TOO_BIG');
}

$filename = pathinfo($_FILES['image']['name'], PATHINFO_FILENAME);
$filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename);
$path = null;

$imgBlob = file_get_contents($tmpPath);
$imgHash = hash('sha256', $imgBlob);

// Checking whether the same image already exists in the database
if ($userId !== null && imgAlreadyInDataBase($imgHash, $userId)) {

    $imgId = getImgIdInDatabase($imgHash, $userId);

    if ($imgId === null) {
        redirectWithError('UPLOAD_FAILED');
    }

    $_SESSION['image_id'] = $imgId;

    $_SESSION['image_obj'] = new Image(
        $imgId,
        $path,
        $filename,
        $fileSize,
        $width,
        $height,
        $mimeType,
        $imgHash,
    );

    $_SESSION['order_obj'] = new Order(
        null,
        $userId,
        $imgId,
        null,
        null,
        null,
        "pending",
        null,
        null,
        null,
        null,
        null,
        null
    );

    redirectWithError('IMAGE_ALREADY_UPLOADED');
    exit;
}

// Database insertion
try {
    // Everything is OK -> insertion into database and save
    $cnx->beginTransaction();

    $sql = "INSERT INTO images (user_id, filename, path, width, height, mime_type, size_bytes, image_blob, created_at, img_hash) 
                VALUES (:user_id, :name, :path, :width, :height, :mime_type, :size, :img_blob, :created_at, :img_hash)";
    $stmt = $cnx->prepare($sql);

    $created_at = date('Y-m-d H:i:s');
    $stmt->bindParam(':user_id', $userId, $userId !== null ? PDO::PARAM_INT : PDO::PARAM_NULL); // $id_client
    $stmt->bindParam(':name', $filename, PDO::PARAM_STR);
    $stmt->bindParam(':path', $path, PDO::PARAM_STR);
    $stmt->bindParam(':width', $width, PDO::PARAM_INT);
    $stmt->bindParam(':height', $height, PDO::PARAM_INT);
    $stmt->bindParam(':mime_type', $mimeType, PDO::PARAM_STR);
    $stmt->bindParam(':size', $fileSize, PDO::PARAM_INT);
    $stmt->bindParam(':img_blob', $imgBlob, PDO::PARAM_LOB);
    $stmt->bindParam(':created_at', $created_at, PDO::PARAM_STR);
    $stmt->bindParam(':img_hash', $imgHash, PDO::PARAM_STR);

    $stmt->execute();

    $imgId = $cnx->lastInsertId();
    $cnx->commit();
} catch (Throwable $e) {
    if ($cnx->inTransaction()) {
        $cnx->rollBack();
    }

    redirectWithError('UPLOAD_FAILED');
}

// Session Object
$_SESSION['image_id'] = $imgId;
$_SESSION['imported'] = true;

$_SESSION['image_obj'] = new Image(
    $imgId,
    $path,
    $filename,
    $fileSize,
    $width,
    $height,
    $mimeType,
    $imgHash,
);

$_SESSION['order_obj'] = new Order(
    null,
    $userId,
    $imgId,
    null,
    null,
    null,
    "pending",
    null,
    null,
    null,
    null,
    null,
    null
);

header("Location: ../../public/preview.php");
exit;
