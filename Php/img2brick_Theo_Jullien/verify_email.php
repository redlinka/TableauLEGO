<?php
// Page that verifies an account's email address with a 64-character token
session_start();
require_once "connection.inc.php";
$cnx = getConnection();

if (!isset($_GET["token"])) {
    header("Location: connection.php?error=token2");
    exit();
}

$token = $_GET["token"];
$tokenHash = hash("sha256", $token);

try {
    $stmt = $cnx->prepare("SELECT * FROM user_token WHERE token = :token AND type = 'email' AND expires_at > NOW()");
    $stmt->bindParam(":token", $tokenHash);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        // Verify the user
        $update = $cnx->prepare("UPDATE user SET is_verified = 1 WHERE id = :user_id");
        $update->bindParam(":user_id", $row["user_id"]);
        $update->execute();

        // Delete the token
        $delete = $cnx->prepare("DELETE FROM user_token WHERE user_id = :user_id AND type = 'email'");
        $delete->bindParam(":user_id", $row["user_id"]);
        $delete->execute();

        unset($_SESSION["temp_user_id"]);

        header("Location: connection.php?registration_success=1");
    } else {
        print("error");
        //header("Location: connection.php?error=token2");
    }
    exit();
} catch (PDOException $e) {
    //echo $e->getMessage(); // in dev
    header("Location: connection.php?error=db_register");
    exit();
}
