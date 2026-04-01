<?php
require_once __DIR__ . '/config/session.php';
global $cnx;
include("./config/cnx.php");
require_once __DIR__ . '/config/games_api.php';
require_once __DIR__ . '/includes/i18n.php';

if (!isset($_SESSION['userId'])) {
    header("Location: connexion.php");
    exit;
}

$userId = (int)$_SESSION['userId'];
$username = $_SESSION['username'] ?? '';

$loyaltyBalance = 0;
$loyaltyLifetime = 0;
$recentEntries = [];
$loyaltyTiers = [];
$maxDiscountPercent = 50;
$loyaltyAvailable = false;

$gamesToken = games_exchange_token($userId, $username);
if ($gamesToken) {
    $balanceData = games_get_balance($gamesToken);
    if ($balanceData && isset($balanceData['summary'])) {
        $loyaltyBalance = (int)$balanceData['summary']['balance'];
        $loyaltyLifetime = (int)($balanceData['summary']['lifetimeEarned'] ?? 0);
        $recentEntries = $balanceData['recentEntries'] ?? [];
        $loyaltyAvailable = true;
    }
    $tiersData = games_get_tiers();
    if ($tiersData) {
        $loyaltyTiers = $tiersData['tiers'] ?? [];
        $maxDiscountPercent = $tiersData['maxDiscountPercent'] ?? 50;
    }
}

$playUrl = games_play_url($userId, $username);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mes Points de Fidelite</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .points-hero { background: linear-gradient(135deg, #2563eb 0%, #7c3aed 100%); color: #fff; border-radius: 16px; padding: 32px; text-align: center; margin-bottom: 24px; }
        .points-hero .balance { font-size: 3rem; font-weight: 800; }
        .points-hero .sub { opacity: 0.8; }
        .tier-card { border: 2px solid #e5e7eb; border-radius: 12px; padding: 16px; text-align: center; transition: border-color 0.2s; }
        .tier-card.active { border-color: #2563eb; background: #eff6ff; }
        .tier-card.locked { opacity: 0.45; }
        .tier-points { font-size: 1.5rem; font-weight: 700; color: #2563eb; }
        .tier-discount { font-size: 2rem; font-weight: 800; }
        .history-entry { padding: 12px 0; border-bottom: 1px solid #f3f4f6; }
        .history-entry:last-child { border-bottom: none; }
        .delta-pos { color: #16a34a; font-weight: 700; }
        .delta-neg { color: #dc2626; font-weight: 700; }
        .play-banner { background: linear-gradient(135deg, #f59e0b 0%, #ef4444 100%); color: #fff; border-radius: 14px; padding: 24px; text-align: center; }
        .play-banner a { color: #fff; background: rgba(255,255,255,0.2); border: 2px solid #fff; padding: 10px 28px; border-radius: 10px; text-decoration: none; font-weight: 700; display: inline-block; margin-top: 12px; }
        .play-banner a:hover { background: rgba(255,255,255,0.35); }
        .progress-bar-tiers { height: 12px; border-radius: 6px; background: #e5e7eb; overflow: hidden; margin: 16px 0; }
        .progress-bar-fill { height: 100%; background: linear-gradient(90deg, #2563eb, #7c3aed); border-radius: 6px; transition: width 0.5s; }
    </style>
</head>
<body>
    <?php include("./includes/navbar.php"); ?>

    <div class="container py-4" style="max-width:900px;">
        <h1 class="fw-bold mb-4">Mes Points de Fidelite</h1>

        <?php if (!$loyaltyAvailable): ?>
            <div class="alert alert-warning">
                Impossible de recuperer vos points pour le moment. Veuillez reessayer plus tard.
            </div>
        <?php else: ?>

        <!-- Balance hero -->
        <div class="points-hero">
            <div class="sub">Votre solde actuel</div>
            <div class="balance"><?= number_format($loyaltyBalance) ?> pts</div>
            <div class="sub">Total gagne : <?= number_format($loyaltyLifetime) ?> pts</div>
        </div>

        <!-- Progress toward tiers -->
        <?php if (!empty($loyaltyTiers)):
            $maxTierPoints = end($loyaltyTiers)['points'];
            $progressPct = min(100, round($loyaltyBalance / $maxTierPoints * 100));
        ?>
        <div class="progress-bar-tiers">
            <div class="progress-bar-fill" style="width: <?= $progressPct ?>%;"></div>
        </div>
        <?php endif; ?>

        <!-- Tier cards -->
        <h5 class="fw-bold mb-3">Paliers de reduction</h5>
        <div class="row g-3 mb-4">
            <?php foreach ($loyaltyTiers as $tier):
                $canAfford = $loyaltyBalance >= (int)$tier['points'];
                $isActive = $canAfford;
            ?>
            <div class="col-md-4">
                <div class="tier-card <?= $isActive ? 'active' : 'locked' ?>">
                    <div class="tier-points"><?= (int)$tier['points'] ?> pts</div>
                    <div class="tier-discount"><?= (int)$tier['discountPercent'] ?>%</div>
                    <div class="text-muted small">de reduction</div>
                    <?php if ($canAfford): ?>
                        <span class="badge bg-success mt-2">Disponible</span>
                    <?php else: ?>
                        <span class="badge bg-secondary mt-2">Encore <?= (int)$tier['points'] - $loyaltyBalance ?> pts</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Play banner -->
        <div class="play-banner mb-4">
            <h4 class="fw-bold">Gagnez plus de points en jouant !</h4>
            <p>Chaque partie vous rapporte des points. Plus vous jouez, plus vous economisez.</p>
            <a href="<?= htmlspecialchars($playUrl) ?>">Jouer maintenant</a>
        </div>

        <!-- Recent history -->
        <h5 class="fw-bold mb-3">Historique recent</h5>
        <?php if (empty($recentEntries)): ?>
            <p class="text-muted">Aucune activite pour le moment. Jouez pour gagner vos premiers points !</p>
        <?php else: ?>
        <div class="card">
            <div class="card-body">
                <?php foreach ($recentEntries as $entry): ?>
                <div class="history-entry d-flex justify-content-between align-items-center">
                    <div>
                        <div class="fw-bold"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $entry['reason'] ?? $entry['entryType']))) ?></div>
                        <div class="text-muted small"><?= htmlspecialchars(date('d/m/Y H:i', strtotime($entry['createdAt']))) ?></div>
                    </div>
                    <div class="<?= ($entry['pointsDelta'] ?? 0) >= 0 ? 'delta-pos' : 'delta-neg' ?>">
                        <?= ($entry['pointsDelta'] ?? 0) >= 0 ? '+' : '' ?><?= (int)($entry['pointsDelta'] ?? 0) ?> pts
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php endif; ?>
    </div>

    <script src="assets/i18n.js"></script>
</body>
</html>
