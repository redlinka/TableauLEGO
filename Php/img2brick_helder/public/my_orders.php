<?php
require_once "../connexion.inc.php";
require_once "../src/Model/Order.php";
require_once "../src/Model/Image.php";

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?error=" . urlencode("You must be signed in to view your orders."));
    exit;
}

$user_id = $_SESSION['user_id'];

$stmt = $cnx->prepare("SELECT o.id as order_id, o.created_at, o.status, o.price_cents, o.address, o.palette_choice, o.size,
                        i.image_blob as image_blob, i.mime_type
                        FROM orders o
                        LEFT JOIN images i ON i.id = o.image_id
                        WHERE o.user_id = :user_id
                        ORDER BY o.created_at DESC");
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->execute();
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Img2Brick</title>

    <link rel="stylesheet" href="./assets/css/style.css">
    <link rel="stylesheet" href="./assets/css/my_orders.css">

    <script>
        function toggleDetails(id) {
            const row = document.getElementById('details-' + id);
            row.style.display = row.style.display === 'table-row' ? 'none' : 'table-row';
        }
    </script>
</head>

<body>
    <div class="bg-circle"></div>
    <header class="c-nav">
        <h2>Img2Brick</h2>
        <nav>
            <ul>
                <li><a href="index.php">Home</a></li>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li><a href="">My Orders</a></li>
                <?php endif; ?>
            </ul>
        </nav>
        <div class="right">
            <?php
            if (isset($_SESSION['user_id'])) { //if already connected
                echo '<a href="account.php" class="profil"><img width="30" height="30" src="https://img.icons8.com/?size=100&id=85147&format=png&color=ffffff" /></a>';
            } else {
                echo '<a href="sign-in.php" class="sign-in">Sign in</a>';
            }
            ?>
        </div>
    </header>
    <div class="container">
        <h1>My Orders</h1>
        <p>Find all your previous orders here.</p>
        <div class="c-table">
            <table>
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Size & Palette</th>
                        <th>Amount</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td>CMD-<?= str_pad($order['order_id'], 5, '0', STR_PAD_LEFT) ?></td>
                            <td><?= $order['created_at'] ?></td>
                            <td><?= ucfirst($order['status']) ?></td>
                            <td><?= $order['size'] ?>, <?= $order['palette_choice'] ?></td>
                            <td><?= number_format($order['price_cents'] / 100, 2, ',', ' ') ?> €</td>
                            <td><button class="toggle-btn" onclick="toggleDetails(<?= $order['order_id'] ?>)">See details</button></td>
                        </tr>
                        <tr id="details-<?= $order['order_id'] ?>" class="details">
                            <td colspan="6">
                                <div class="details-content">
                                    <strong>Delivery address:</strong> <?= htmlspecialchars($order['address']) ?><br>
                                    <strong>Selected image:</strong><br>
                                    <img src="data:<?= htmlspecialchars($order['mime_type']) ?>;base64,<?= base64_encode($order['image_blob']) ?>" alt="Image"><br>
                                    <strong>Number of colors:</strong> <?= $order['nb_colors'] ?? '20' ?><br>
                                    <strong>Downloadable documents:</strong>
                                    <ul>
                                        <li><a href="#">PDF plan</a> (if available)</li>
                                        <li><a href="#">Piece list(CSV/Excel)</a> (if available)</li>
                                        <li><a href="#">Final image</a></li>
                                    </ul>
                                    <button onclick="alert('Contact customer service')" class="toggle-btn">Contact customer service</button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <footer>
        <a href="https://github.com/Draken1003/img2brick" target="_blank">GitHub</a>
    </footer>

    <script>
        function toggleDetails(orderId) {
            const row = document.getElementById('details-' + orderId);
            const content = row.querySelector('.details-content');

            if (row.style.display === 'table-row') {
                content.classList.remove('open');
                setTimeout(() => {
                    row.style.display = 'none';
                }, 400);
            } else {
                row.style.display = 'table-row';
                requestAnimationFrame(() => {
                    content.classList.add('open');
                });
            }
        }
    </script>
</body>

</html>