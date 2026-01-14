<?php
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
    session_start();
    global $cnx;
    include("./config/cnx.php");
    require_once __DIR__ . '/includes/i18n.php';

    if (!isset($_SESSION['userId']) || !isset($_SESSION['username'])) {
        header("Location: connexion.php");
        exit;
    }
    $navUsername = $_SESSION['username'];
    if ($navUsername != '4DM1N1STRAT0R_4ND_4LM16HTY') {
        header("Location: index.php");
        exit;
    }
$message = "";
$messageType = "";

// 2. HANDLE RESTOCK FORM SUBMISSION
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'restock') {

    // Create a temporary file
    $tempFileName = "restock_" . time() . "_" . rand(1000,9999) . ".txt";
    $tempFilePath = __DIR__ . "/" . $tempFileName;

    // Open file for writing
    $fileHandle = fopen($tempFilePath, 'w');

    if ($fileHandle) {
        // Write ignored header line (as per result.txt format)
        fwrite($fileHandle, "HEADER_IGNORED\n");

        $totalItems = 0;

        // Loop through the submitted quantities
        // The input names are formatted as: quantity[width-height/hex]
        if (isset($_POST['quantity']) && is_array($_POST['quantity'])) {
            foreach ($_POST['quantity'] as $key => $qty) {
                $qty = (int)$qty;
                if ($qty > 0) {
                    // key format: "1-1/354e5a"
                    // result.txt line format needed: "1-1/354e5a,0,0,0"
                    // We don't care about rotation/x/y (0,0,0), but they must be there for the format.
                    $lineContent = $key . ",0,0,0\n";

                    // Write the line N times (since ManualRestock adds 1 per line)
                    for ($i = 0; $i < $qty; $i++) {
                        fwrite($fileHandle, $lineContent);
                    }
                    $totalItems += $qty;
                }
            }
        }

        fclose($fileHandle);

        if ($totalItems > 0) {
            // 3. EXECUTE JAVA COMMAND (Using brain.jar logic)

            // Define paths exactly like in add_cart.php
            $jarPath = __DIR__ . '/brain.jar';
            $javaCmd = 'java';

            // Windows-specific configuration
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $javaCmd = '"C:\\Program Files\\Eclipse Adoptium\\jdk-25.0.1.8-hotspot\\bin\\java.exe"';
            }

            // Execute only if JAR and Temp File exist
            if (file_exists($jarPath) && file_exists($tempFilePath)) {
                // Command structure: java -cp brain.jar ManualRestock [tempFile]
                // We assume ManualRestock is compiled INSIDE brain.jar or available in its context
                $cmd = sprintf(
                    '%s -cp %s fr.uge.univ_eiffel.ManualRestock %s 2>&1',
                    $javaCmd,
                    escapeshellarg($jarPath),
                    escapeshellarg($tempFilePath)
                );

                // Debug: Uncomment to see the generated command
                // echo $cmd; exit;

                $output = [];
                $returnVar = 0;
                exec($cmd, $output, $returnVar);

                if ($returnVar === 0) {
                    $message = "Success! Ordered $totalItems items. Stock updated.";
                    $messageType = "success";
                } else {
                    $javaError = implode("\n", $output);
                    $message = "Error executing Java command. Return code: $returnVar. <br><strong>Java Error:</strong> <pre>$javaError</pre>";
                    $messageType = "danger";
                }
            } else {
                $message = "Error: brain.jar not found at $jarPath";
                $messageType = "danger";
            }
        } else {
            $message = "No items selected.";
            $messageType = "warning";
        }

        // 4. CLEANUP
        if (file_exists($tempFilePath)) {
            unlink($tempFilePath);
        }

    } else {
        $message = "Could not create temporary file.";
        $messageType = "danger";
    }
}

// 3. FETCH DATA FOR TABS
// Clients
$stmtUsers = $cnx->query("SELECT user_id, email, first_name, last_name FROM USER ORDER BY user_id DESC");
$users = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

// Orders
$sqlOrders = "
    SELECT o.order_id, o.created_at, o.user_id, u.first_name, u.last_name, u.email 
    FROM ORDER_BILL o 
    JOIN USER u ON o.user_id = u.user_id 
    WHERE o.created_at IS NOT NULL 
    ORDER BY o.created_at DESC
";
$stmtOrders = $cnx->query($sqlOrders);
$orders = $stmtOrders->fetchAll(PDO::FETCH_ASSOC);

$sqlCatalog = "SELECT * FROM catalog_with_price_and_stock ORDER BY stock ASC";
$stmtCatalog = $cnx->query($sqlCatalog);
$catalog = $stmtCatalog->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - Img2Brick</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; padding-top: 20px; }
        .color-box { display: inline-block; width: 20px; height: 20px; border: 1px solid #ccc; vertical-align: middle; margin-right: 5px; }
        .nav-tabs .nav-link.active { font-weight: bold; border-bottom: 3px solid #0d6efd; }
        .table-container { background: white; padding: 20px; border-radius: 0 0 8px 8px; border: 1px solid #dee2e6; border-top: none; }
        /* Sticky header for the huge catalog table */
        .catalog-table-wrapper { max-height: 600px; overflow-y: auto; }
        .catalog-table thead th { position: sticky; top: 0; background: white; z-index: 1; box-shadow: 0 2px 2px -1px rgba(0,0,0,0.4); }
    </style>
</head>
<body>
<div class="container-fluid px-5">
    <?php include("./includes/navbar.php"); ?>

    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <ul class="nav nav-tabs" id="adminTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="clients-tab" data-bs-toggle="tab" data-bs-target="#clients" type="button" role="tab">👥 Clients</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="orders-tab" data-bs-toggle="tab" data-bs-target="#orders" type="button" role="tab">📦 Orders</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="restock-tab" data-bs-toggle="tab" data-bs-target="#restock" type="button" role="tab">🏗️ Restock Form</button>
        </li>
    </ul>

    <div class="tab-content" id="adminTabsContent">

        <div class="tab-pane fade show active" id="clients" role="tabpanel">
            <div class="table-container">
                <table class="table table-hover">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td>#<?= $u['user_id'] ?></td>
                            <td><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></td>
                            <td><?= htmlspecialchars($u['email']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="tab-pane fade" id="orders" role="tabpanel">
            <div class="table-container" style="background: transparent; border: none; padding: 0; padding-top: 20px;">

                <?php if (count($orders) === 0): ?>
                    <div class="alert alert-info">No orders found.</div>
                <?php else: ?>

                    <?php foreach ($orders as $order):
                        // 1. REUSE LOGIC FROM MY_ORDERS.PHP
                        // We fetch items via the CONTAIN table (Order -> Contain -> Pavage -> Image)
                        $sqlItems = "
                                SELECT P.pavage_txt, I.path
                                FROM contain C
                                JOIN TILLING P ON C.pavage_id = P.pavage_id
                                JOIN IMAGE I ON P.image_id = I.image_id
                                WHERE C.order_id = ?
                            ";
                        $stmtItems = $cnx->prepare($sqlItems);
                        $stmtItems->execute([$order['order_id']]);
                        $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

                        // Calculate Totals on the fly (since they aren't in the DB)
                        $orderTotal = 0;
                        $dateFormatted = date('d M Y, H:i', strtotime($order['created_at']));
                        ?>

                        <div class="admin-order-group">
                            <div class="admin-order-header">
                                <div>
                                    <span>Order #<?= $order['order_id'] ?></span>
                                    <span class="text-muted fw-normal mx-2">by</span>
                                    <span class="text-primary"><?= htmlspecialchars($order['first_name'] . ' ' . $order['last_name']) ?></span>
                                    <small class="text-muted">(<?= htmlspecialchars($order['email']) ?>)</small>
                                </div>
                                <div><?= $dateFormatted ?></div>
                            </div>

                            <?php foreach ($items as $item):
                                $stats = getTilingStats($item['pavage_txt']);
                                // Price logic from my_orders.php
                                $price = isset($stats['price']) ? $stats['price'] / 100 : 0;
                                $orderTotal += $price;

                                // Path fix: my_orders says 'users/imgs/' + path
                                $imgPath = "users/imgs/" . $item['path'];
                                ?>
                                <div class="item-row item-row-grouped">
                                    <img src="<?= $imgPath ?>" alt="Overview" class="thumb-img">

                                    <div style="flex: 1;">
                                        <strong>File : <?= htmlspecialchars($item['pavage_txt']) ?></strong><br>
                                        <small>Quality : <?= $stats['quality'] ?? 0 ?>%</small>
                                        <br>
                                        <a href="generate_manual.php?file=<?= urlencode($item['pavage_txt']) ?>" target="_blank" class="btn btn-sm btn-outline-primary mt-2">
                                            View Guide
                                        </a>
                                        <a href="users/tilings/<?= htmlspecialchars($item['pavage_txt']) ?>" download class="btn btn-sm btn-outline-secondary mt-2">
                                            Download Tiling
                                        </a>
                                    </div>

                                    <div class="fw-bold"><?= number_format($price, 2) ?> EUR</div>
                                </div>
                            <?php endforeach; ?>

                            <div class="bg-white p-3 border border-top-0 rounded-bottom text-end">
                                <strong>Subtotal : <?= number_format($orderTotal, 2) ?> EUR</strong><br>
                                <small>Shipping costs (10%): <?= number_format($orderTotal * 0.1, 2) ?> EUR</small><br>
                                <strong class="fs-5">Total : <?= number_format($orderTotal * 1.1, 2) ?> EUR</strong>
                            </div>

                        </div>
                    <?php endforeach; ?>

                <?php endif; ?>

            </div>
        </div>

        <div class="tab-pane fade" id="restock" role="tabpanel">
            <div class="table-container">
                <form method="POST" action="admin_panel.php">
                    <input type="hidden" name="action" value="restock">

                    <div class="d-flex justify-content-between mb-3">
                        <h4>Catalog & Restock</h4>
                        <button type="submit" class="btn btn-primary btn-lg">🚀 Order Selected Bricks</button>
                    </div>

                    <div class="catalog-table-wrapper">
                        <table class="table table-sm align-middle catalog-table">
                            <thead class="table-dark">
                            <tr>
                                <th>Ref</th>
                                <th>Color</th>
                                <th>Size</th>
                                <th>Current Stock</th>
                                <th>Price</th>
                                <th style="width: 150px;">Order Qty</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($catalog as $item):
                                // Create Key: width-height/hex (e.g., 1-1/354e5a)
                                $fileKey = $item['width'] . '-' . $item['height'] . '/' . $item['color_hex'];
                                ?>
                                <tr>
                                    <td>#<?= $item['id_catalogue'] ?></td>

                                    <td>
                                        <span class="color-box" style="background-color: #<?= $item['color_hex'] ?>;"></span>
                                        <?= htmlspecialchars($item['color_name']) ?>
                                        <small class="text-muted">(#<?= $item['color_hex'] ?>)</small>
                                    </td>

                                    <td><?= $item['width'] ?> x <?= $item['height'] ?></td>

                                    <td class="<?= $item['stock'] < 10 ? 'text-danger fw-bold' : '' ?>">
                                        <?= $item['stock'] ?>
                                    </td>

                                    <td><?= number_format($item['unit_price'], 2) ?> €</td>

                                    <td>
                                        <input type="number"
                                               name="quantity[<?= $fileKey ?>]"
                                               class="form-control form-control-sm"
                                               min="0"
                                               placeholder="0">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3 text-end">
                        <button type="submit" class="btn btn-primary btn-lg">🚀 Order Selected Bricks</button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

