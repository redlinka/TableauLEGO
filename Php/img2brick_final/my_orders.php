<?php
session_start();
// Include cnx.php so we can use the getTilingStats function
require_once 'cnx.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "img2brick_db");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$user_id = $_SESSION['user_id'];

// 1. Get the orders for this user
$sql = "SELECT order_id, created_at FROM ORDER_BILL WHERE user_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>

    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>My Orders</title>
        <link rel="stylesheet" href="style.css">
        <style>
            /* Minimal inline styles to keep layout if style.css is missing */
            .order-box { border: 1px solid #ccc; padding: 15px; margin-bottom: 20px; border-radius: 5px; }
            .order-header { display: flex; justify-content: space-between; background: #f4f4f4; padding: 10px; border-bottom: 1px solid #ddd; }
            table { width: 100%; border-collapse: collapse; margin-top: 10px; }
            th, td { padding: 8px; border-bottom: 1px solid #eee; text-align: left; }
        </style>
    </head>
    <body>

    <div class="container">
        <h1>My Orders</h1>

        <?php if ($result->num_rows > 0): ?>
            <?php while($order = $result->fetch_assoc()): ?>
                <?php
                // 2. Fetch the tilings for this specific order
                // We join 'contain' with 'TILLING' to get the filename
                $order_id = $order['order_id'];
                $sql_items = "SELECT t.pavage_txt 
                              FROM contain c 
                              JOIN TILLING t ON c.pavage_id = t.pavage_id 
                              WHERE c.order_id = ?";

                $stmt_items = $conn->prepare($sql_items);
                $stmt_items->bind_param("i", $order_id);
                $stmt_items->execute();
                $res_items = $stmt_items->get_result();

                // 3. Pre-calculate data for this order using the file function
                $display_items = [];
                $total_order_price_cents = 0;

                while($row = $res_items->fetch_assoc()) {
                    $filename = $row['pavage_txt'];
                    $filepath = "users/tillings/" . $filename; // Ensure this path matches your folder structure

                    // Default values in case file is missing
                    $price_cents = 0;
                    $quality = 0;

                    // Calculate stats from file
                    if (function_exists('getTilingStats') && file_exists($filepath)) {
                        $stats = getTilingStats($filepath);
                        $price_cents = $stats['price'];
                        $quality = $stats['percent'];
                    }

                    $total_order_price_cents += $price_cents;

                    // Store for display
                    $display_items[] = [
                            'name' => $filename,
                            'price' => $price_cents,
                            'quality' => $quality
                    ];
                }
                ?>

                <div class="order-box">
                    <div class="order-header">
                        <div>
                            <strong>Order #<?php echo htmlspecialchars($order['order_id']); ?></strong>
                            <span style="color: #666; font-size: 0.9em; margin-left: 10px;">
                            <?php echo htmlspecialchars($order['created_at']); ?>
                        </span>
                        </div>
                        <div>
                            <strong>Total: $<?php echo number_format($total_order_price_cents / 100, 2); ?></strong>
                        </div>
                    </div>

                    <table>
                        <thead>
                        <tr>
                            <th>Tiling File</th>
                            <th>Quality</th>
                            <th>Price</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach($display_items as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['name']); ?></td>
                                <td><?php echo number_format($item['quality'], 2); ?>%</td>
                                <td>$<?php echo number_format($item['price'] / 100, 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php endwhile; ?>
        <?php else: ?>
            <p>You have no orders yet.</p>
        <?php endif; ?>
    </div>

    </body>
    </html>

<?php
$conn->close();
?>