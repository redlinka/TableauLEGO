<?php
session_start();
require_once "connection.inc.php";
require_once "src/UserManager.php";
require_once "src/EmailSender.php";

$cnx = getConnection();
$userMgr = new UserManager($cnx);
$mailer = new EmailSender();

// 1. Recovery of order data from POST
$pictureId = $_POST['picture_id'] ?? null;
$filter    = $_POST['filter'] ?? null;
$size      = $_POST['size'] ?? null;
$amount    = $_POST['amount'] ?? null;

// Shipping info
$address   = $_POST['address'] ?? '';
$zip       = $_POST['zip'] ?? '';
$city      = $_POST['city'] ?? '';
$phone     = $_POST['phone'] ?? '';

if (!$pictureId || !$filter || !$size) {
    header("Location: index.php");
    exit;
}

// 2. ACCOUNT CREATION LOGIC (If not logged in)
if (!isset($_SESSION["user_id"])) {
    $email = trim($_POST["email"]);
    $name  = trim($_POST["name"]);
    $pwd   = $_POST["pwd"];

    // Validate password complexity
    if (!$userMgr->validatePassword($pwd)) {
        header("Location: order.php?error=password_weak"); // Redirect back with error
        exit;
    }

    // Basic security check: does user already exist?
    $existingUser = $userMgr->getUserByEmail($email);
    if ($existingUser) {
        // we link the order to existing user or redirect.
        $userId = $existingUser['id'];
    } else {
        try {
            // Create new user
            $hashedPwd = password_hash($pwd, PASSWORD_ARGON2ID);
            $stmt = $cnx->prepare("INSERT INTO user (email, name, password, shipping_address, is_verified) VALUES (:email, :name, :pwd, :address, 0)");
            $stmt->execute([
                ':email'   => $email,
                ':name'    => $name,
                ':pwd'     => $hashedPwd,
                ':address' => "$address, $zip $city"
            ]);
            $userId = $cnx->lastInsertId();
        } catch (PDOException $e) {
            //echo $e->getMessage(); // in dev
            header("Location: index.php?error=db");
        }

    }
    $_SESSION["user_id"] = $userId;
} else {
    $userId = $_SESSION["user_id"];
    $user   = $userMgr->getUserById($userId);
    $email  = $user['email'];
}

// 3. MOCK ORDER
// We update the picture to "commercialized" so it won't be deleted by the event
$stmt = $cnx->prepare("UPDATE picture SET commercialized = 1, user_id = :user_id WHERE id = :id");
$stmt->execute([':user_id' => $userId, ':id' => $pictureId]);
$orderNumber = strtoupper(uniqid("ORD-"));
$fullAddress = "$address, $zip $city";

try {
    $sqlOrder = "INSERT INTO user_order (order_number, user_id, picture_id, filter, size, amount, shipping_address, phone) 
                 VALUES (:order_number, :user_id, :picture_id, :filter, :size, :amount, :address, :phone)";
    $stmtOrder = $cnx->prepare($sqlOrder);
    $stmtOrder->execute([
        ':order_number' => $orderNumber,
        ':user_id'      => $userId,
        ':picture_id'   => $pictureId,
        ':filter'       => $filter,
        ':size'         => $size,
        ':amount'       => $amount,
        ':address'      => $fullAddress,
        ':phone'        => $phone
    ]);

    // Save the picture (prevent deletion by event)
    $stmtPic = $cnx->prepare("UPDATE picture SET commercialized = 1, user_id = :user_id WHERE id = :id");
    $stmtPic->execute([':user_id' => $userId, ':id' => $pictureId]);

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// 4. SEND CONFIRMATION EMAIL
$subject = "Order Confirmation - " . $orderNumber;
$body = "<h2>Thank you for your order!</h2>
         <p>Your mosaic is being prepared.</p>
         <ul>
            <li><strong>Order No:</strong> $orderNumber</li>
            <li><strong>Size:</strong> $size x $size</li>
            <li><strong>Price:</strong> $amount €</li>
            <li><strong>Delivery to:</strong> $address, $zip $city</li>
         </ul>";
$mailer->send($email, $subject, $body);

// 5. CLEANUP SESSION
unset($_SESSION['order_size']);
unset($_SESSION['order_filter']);
unset($_SESSION['order_picture_id']);
unset($_SESSION['allowed_file']);

// 6. Fetch thumbnail for display
$stmtImg = $cnx->prepare("SELECT image_data, fileextension FROM picture WHERE id = :id");
$stmtImg->execute([':id' => $pictureId]);
$picData = $stmtImg->fetch();
$base64 = base64_encode($picData['image_data']);
$mime = ($picData['fileextension'] === 'png') ? 'image/png' : 'image/jpeg';

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Order Confirmed - Img2Brick</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="p-conf">
<?php include "header.inc.php"; ?>

<main class="container">
    <div class="confirmation-box">
        <div class="success-icon">
            <i class="fa-solid fa-circle-check"></i>
        </div>

        <h1>Thank you for your order!</h1>
        <p class="subtitle">
            Your mosaic is being prepared with care.
            A confirmation email has been sent to <strong><?= htmlspecialchars($email) ?></strong>.
        </p>

        <div class="order-recap">
            <div class="recap-header">
                <h3>Order #<?= $orderNumber ?></h3>
                <span class="status-badge">Processing</span>
            </div>

            <div class="recap-content">
                <div class="recap-image">
                    <img class="filter-<?= $filter ?>" src="data:<?= $mime ?>;base64,<?= $base64 ?>" alt="Your Mosaic">
                </div>
                <div class="recap-text">
                    <p><strong>Configuration:</strong> <?= ucfirst($filter) ?> version, <?= $size ?>x<?= $size ?></p>
                    <p><strong>Shipping to:</strong><br>
                        <?= htmlspecialchars($address) ?><br>
                        <?= htmlspecialchars($zip) ?> <?= htmlspecialchars($city) ?>
                    </p>
                    <p class="price-paid">Total Paid: <span><?= $amount ?> €</span></p>
                </div>
            </div>
        </div>

        <div class="conf-actions">
            <a href="index.php" class="btn-home">Create another mosaic</a>
            <a href="orders_history.php" class="btn-secondary">View my orders</a>
        </div>
    </div>
</main>

<?php include "footer.inc.php"; ?>
</body>
</html>