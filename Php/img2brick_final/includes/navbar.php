<?php
global $cnx;
require_once __DIR__ . '/i18n.php';
$isLoggedIn = isset($_SESSION['userId']);
$navUsername = $_SESSION['username'] ?? tr('nav.account_guest', 'Account');
$currentPage = basename($_SERVER['PHP_SELF']);

$number = 0; // Initialisation par défaut
if($isLoggedIn){
    $userId = $_SESSION['userId'];
    $stmt = $cnx->prepare("
        SELECT COUNT(*) AS nb_panier
        FROM ORDER_BILL o
        JOIN contain c ON c.order_id = o.order_id
        WHERE o.user_id = :user_id
        AND o.created_at IS NULL
    ");
    $stmt->execute(['user_id' => $userId]);
    $number = (int) $stmt->fetchColumn();
}
?>

<link rel="stylesheet" href="assets/style.css">

<nav class="site-nav">
    <div class="nav-container">

        <a class="nav-brand" href="index.php">
            <img src="assets/images/logo2.png" alt="Img2Brick" class="nav-logo">
        </a>

        <div class="nav-actions">

            <?php if ($isLoggedIn): ?>
                <a href="index.php#start-creating" class="nav-btn btn-primary <?= ($currentPage == 'index.php') ? 'active' : '' ?>" data-i18n="nav.create">Create</a>

                <div class="cart-section">
                    <a href="cart.php" class="cart-link" aria-label="Open orders">
                        <img src="assets/images/cart.png" alt="Cart" class="cart-icon">
                        <?php if ($number > 0): ?>
                            <span class="cart-badge"><?= ($number > 9) ? "9+" : $number ?></span>
                        <?php endif; ?>
                    </a>
                </div>
            <?php endif; ?>

            <div class="language-section">
                <select id="langSelect" class="lang-select">
                    <option value="fr" data-i18n="nav.lang.fr">Français</option>
                    <option value="en" data-i18n="nav.lang.en">English</option>
                    <option value="es" data-i18n="nav.lang.es">Español</option>
                </select>
            </div>

            <div class="account-actions">
                <?php if ($isLoggedIn): ?>
                    <a href="mes_points.php" class="nav-btn btn-secondary <?= ($currentPage == 'mes_points.php') ? 'active' : '' ?>" style="background:#2563eb;color:#fff;border-color:#2563eb;">Mes Points</a>
                    <a href="my_orders.php" class="nav-btn btn-secondary <?= ($currentPage == 'my_orders.php') ? 'active' : '' ?>" data-i18n="nav.my_orders">My Orders</a>

                    <?php if ($navUsername != '4DM1N1STRAT0R_4ND_4LM16HTY'): ?>
                        <a href="my_account.php" class="nav-btn btn-secondary <?= ($currentPage == 'my_account.php') ? 'active' : '' ?>">
                            <?= htmlspecialchars($navUsername) ?>
                        </a>
                    <?php else: ?>
                        <a href="admin_panel.php" class="nav-btn btn-secondary <?= ($currentPage == 'admin_panel.php') ? 'active' : '' ?>">Admin Panel</a>
                    <?php endif; ?>

                    <a href="logout.php" class="nav-btn btn-danger" data-i18n="nav.logout">Log Out</a>

                <?php else: ?>
                    <a href="connexion.php" class="nav-btn btn-secondary" data-i18n="nav.login">Log In</a>
                    <a href="creation.php" class="nav-btn btn-primary" data-i18n="nav.signup">Sign Up</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<script src="assets/i18n.js"></script>