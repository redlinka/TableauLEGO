<?php
global $cnx;
require_once __DIR__ . '/i18n.php';
$isLoggedIn = isset($_SESSION['userId']);
$navUsername = $_SESSION['username'] ?? tr('nav.account_guest', 'Account');
$currentPage = basename($_SERVER['PHP_SELF']);

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
<style>
.cart-wrapper{
  position: relative;
  display: inline-block;
}

.cart-icon{
  width: 60px;
  height: 60px;
}

.cart-badge{
  position: absolute;
  top: -6px;
  right: -6px;
  min-width: 20px;
  height: 20px;
  padding: 0 6px;
  background: #e60023;
  color: #fff;
  font-size: 12px;
  font-weight: 700;
  border-radius: 999px;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 0 0 2px #fff;
  pointer-events: none; 
}
</style>

<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm mb-4">
    <div class="container-fluid px-3">
        <a class="navbar-brand fw-bold" href="index.php" data-i18n="brand.name">TableauLEGO</a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent" aria-controls="navbarContent" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarContent">

            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
            </ul>

            <div class="d-flex flex-column flex-lg-row align-items-lg-center gap-2 mt-3 mt-lg-0">
                <?php if ($isLoggedIn): ?>
                        <div class="cart-profil">
                            <a href="cart.php" class="cart-wrapper" aria-label="Open orders">
                                <img src="users\imgs_site\cart.png" alt="Panier" class="cart-icon">
                                <?php if ($number > 0): ?>
                                    <span class="cart-badge"><?= ($number > 9) ? "9+" : $number ?></span>
                                <?php endif; ?>
                            </a>
                        </div>
                        <a href="my_orders.php" class="btn btn-outline-secondary <?= ($currentPage == 'my_orders.php') ? 'active' : '' ?>" data-i18n="nav.my_orders">
                            My Orders
                        </a>
                    <?php if ($navUsername != '4DM1N1STRAT0R_4ND_4LM16HTY'): ?>
                        <a href="my_account.php" class="btn btn-outline-secondary <?= ($currentPage == 'my_account.php') ? 'active' : '' ?>">
                            <?= htmlspecialchars($navUsername) ?>
                        </a>
                    <?php else: ?>
                        <a href="admin_panel.php" class="btn btn-outline-secondary <?= ($currentPage == 'admin_panel.php') ? 'active' : '' ?>">
                            Admin Panel
                        </a>
                    <?php endif; ?>

                    <a href="logout.php" class="btn btn-outline-danger" data-i18n="nav.logout">Log Out</a>

                <?php else: ?>

                    <a href="connexion.php" class="btn btn-outline-primary" data-i18n="nav.login">Log In</a>
                    <a href="creation.php" class="btn btn-primary" data-i18n="nav.signup">Sign Up</a>

                <?php endif; ?>

                <div class="d-flex align-items-center gap-2 mt-2 mt-lg-0">
                    <label class="small text-muted mb-0" for="langSelect" data-i18n="nav.language">Language</label>
                    <select id="langSelect" class="form-select form-select-sm w-auto">
                        <option value="fr" data-i18n="nav.lang.fr">Francais</option>
                        <option value="en" data-i18n="nav.lang.en">English</option>
                        <option value="es" data-i18n="nav.lang.es">Espanol</option>
                    </select>
                </div>
            </div>
        </div>
    </div>
</nav>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/i18n.js"></script>
