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
            // 3. EXECUTE JAVA COMMAND
            // TWEAK 1: Add "2>&1" at the end. This forces Java errors to be captured in $output
            $command = "java -cp .:./mysql-connector-j-9.1.0.jar ManualRestock " . escapeshellarg($tempFileName) . " 2>&1";

            // Debug: Uncomment this line to see the exact command being run if needed
            // echo "Executing: $command"; exit;

            $output = [];
            $returnVar = 0;
            exec($command, $output, $returnVar);

            if ($returnVar === 0) {
                $message = "Success! Ordered $totalItems items. Stock updated.";
                $messageType = "success";
            } else {
                // TWEAK 2: Print the captured output so you can read the Java Exception
                $javaError = implode("\n", $output);
                $message = "Error executing Java command. Return code: $returnVar. <br><strong>Java Error:</strong> <pre>$javaError</pre>";
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
$stmtOrders = $cnx->query("SELECT * FROM ORDER_BILL ORDER BY created_at DESC LIMIT 50");
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
            <div class="table-container">
                <table class="table table-striped">
                    <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Date</th>
                        <th>User ID</th>
                        <th>Details</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($orders as $o): ?>
                        <tr>
                            <td><?= $o['order_id'] ?></td>
                            <td><?= $o['created_at'] ?></td>
                            <td><?= $o['user_id'] ?></td>
                            <td>
                                <button class="btn btn-sm btn-info text-white">View Details</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
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

