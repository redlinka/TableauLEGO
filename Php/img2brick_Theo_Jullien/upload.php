<?php
session_start();
require_once "connection.inc.php";
$cnx = getConnection();
date_default_timezone_set('Europe/Paris'); // so that the PHP server uses the same time zone as MySQL

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_FILES["image"])) {
    $file = $_FILES["image"];
    $fileName = basename($file["name"]); // keep the last element of a path
    $fileTmpPath = $file["tmp_name"];
    $fileSize = $file["size"];
    $fileError = $file["error"];
    $imageData = file_get_contents($fileTmpPath);

    // Allowed extensions
    $allowedExtensions = ["jpg", "jpeg", "png", "webp"];
    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION)); // extract file extension

    if (!in_array($fileExtension, $allowedExtensions)) {
        header("Location: index.php?error=extension");
        exit;
    }
    if ($fileError !== 0){
        header("Location: index.php?error=fileerror");
        exit;
    }
    // verify the size of file
    $maxFileSize = 2 * 1024 * 1024; // 2 MB in bytes; the php.init max upload file size is set to 2MO
    if ($fileSize > $maxFileSize) {
        header("Location: index.php?error=filesize");
        exit;
    }

    // size of file name  (100 characters):
    if (mb_strlen($fileName, 'UTF-8') > 100) {
        header("Location: index.php?error=filename");
        exit;
    }
    $newFileName = uniqid('img_', true) . '.' . $fileExtension; // permit to avoid collisions between the names of pictures from different users

    try {
        $userId = $_SESSION["user_id"] ?? null;
        $query = $cnx->prepare("INSERT INTO picture (image_data, filename, filesize, fileextension, originalname, date, user_id) VALUES (:image_data, :filename, :filesize, :fileextension, :originalname, NOW(), :user_id)");
        $query->bindParam(":image_data", $imageData, PDO::PARAM_LOB);
        $query->bindParam(":filename", $newFileName);
        $query->bindParam(":filesize", $fileSize);
        $query->bindParam(":fileextension", $fileExtension);
        $query->bindParam(":originalname", $fileName);
        if ($userId !== null) {
            $query->bindParam(":user_id", $userId, PDO::PARAM_INT);
        } else {
            $query->bindValue(":user_id", null, PDO::PARAM_NULL);
        }
        $query->execute();
        $_SESSION["allowed_file"] = $newFileName;
        $_SESSION["allowed_file_expire"] = time() + 3600; // 1hour
        header("Location: validation.php");
        exit;
    } catch (PDOException $e) {
        //echo $e->getMessage(); // in dev
        header("Location: index.php?error=db"); // in prod
        exit;
    }

} else {
    header("Location: index.php?error=nofile");
    exit;
}