<?php
session_start();
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
$sql_orders = "SELECT order_id, created_at FROM ORDER_BILL WHERE user_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($sql_orders);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result_orders = $stmt->get_result();
?>

    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>My Orders</title>
        <style>
            body { font-family: sans-serif; }
            .order-container { border: 1px solid #ccc; margin-bottom: 20px; padding: 10px; }
            .order-header { background-color: #f9f9f9; padding: 10px; border-bottom: 1px solid #ddd; display: flex; justify-content: space-between; }
            .order-items { margin-top: 10px; }
            table { width: 100%; border-collapse: collapse; }
            th, td { text-align: left; padding: 8px; border-bottom: 1px solid #eee; }
        </style>
    </head>
    <body>

    <h1>My Orders</h1>

    <?php if ($result_orders->num_rows > 0): ?>
        <?php while($order = $result_orders->fetch_assoc()):
            $sql_items = "SELECT t.pavage_txt 
                      FROM TILLING t 
                      JOIN contain c ON t.pavage_id = c.pavage_id 
                      WHERE c.order_id = ?";

            $stmt_items = $conn->prepare($sql_items);
            $stmt_items->bind_param("i", $order['order_id']);
            $stmt_items->execute();
            $result_items = $stmt_items->get_result();

            $order_items_data = [];
            $total_price_cents = 0;

            while($item = $result_items->fetch_assoc()) {
                $path = 'users/tillings/' . $item['pavage_txt'];
                $stats = getTilingStats($path);

                $total_price_cents += $stats['price'];
                $order_items_data[] = [
                        'name' => $item['pavage_txt'],
                        'price' => $stats['price'],
                        'percent' => $stats['percent']
                ];
            }
            ?>
            <div class="order-container">
                <div class="order-header">
                    <div>
                        <strong>Order #<?php echo htmlspecialchars($order['order_id']); ?></strong>
                        <br>
                        <small><?php echo htmlspecialchars($order['created_at']); ?></small>
                    </div>
                    <div>
                        <strong>Total: $<?php echo number_format($total_price_cents / 100, 2); ?></strong>
                    </div>
                </div>
                <div class="order-items">
                    <table>
                        <thead>
                        <tr>
                            <th>Tiling File</th>
                            <th>Quality</th>
                            <th>Price</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach($order_items_data as $data): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($data['name']); ?></td>
                                <td><?php echo round($data['percent'], 2); ?>%</td>
                                <td>$<?php echo number_format($data['price'] / 100, 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <p>You have no orders yet.</p>
    <?php endif; ?>

    </body>
    </html>
<?php
$conn->close();
?>