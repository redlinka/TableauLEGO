<?php
function get_image(PDO $pdo, int $id_img) {
    $stmt = $pdo->prepare("SELECT * FROM img WHERE id_img = :id");
    $stmt->execute([":id" => $id_img]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $base64 = base64_encode($row['image']);
    $src = "data:image/jpeg;base64,{$base64}";

    $base64_2 = base64_encode($row['pavage']);
    $src_2 = "data:image/jpeg;base64,{$base64_2}";

    $img = new image($row["id_img"], $src, $row["date"], $src_2, $row["prix"]);

    return $img;
    }

function get_user(PDO $pdo, int $id_user) {
    $stmt = $pdo->prepare("SELECT * FROM utilisateur WHERE code_uti = :id");
    $stmt->execute([":id" => $id_user]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $user = new user($row["code_uti"], $row["nom"], $row["adresse_mail"], $row["solde"], $row["numero_telephone"]);

    return $user;
    }

function passworld_correct(string $mdp) {
    return preg_match('/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[^A-Za-z0-9])[\S]{12,}$/',$mdp);
    }

function all_pan(PDO $pdo, int $id_user){
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(nb), 0) AS total FROM panier WHERE code_uti = :id");
    $stmt->execute([':id' => $id_user]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return (int)$row['total'];
}

function money($v) {
    return number_format((float)$v, 2, ".", " ") . " €";
}

function simulated_payment_ok(string $card, string $exp, string $cvc): bool {
    $card = preg_replace('/\s+/', '', $card); 
    return $card === "4242424242424242" && $exp === "12/34" && $cvc === "123";
}

/*A17F44G6FGOQM009QJSAAATITS0G7QA6BMM3SQK0E6KB5BJ10K6AG66LMN*/ 
?>
