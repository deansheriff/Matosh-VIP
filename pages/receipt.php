<?php
require_once '../includes/auth.php';
require_once '../config/db.php';

$order_id = $_GET['id'] ?? null;

if (!$order_id) {
    die('Order ID is required.');
}

try {
    // Fetch company settings
    $company_stmt = $pdo->query("SELECT * FROM company_settings WHERE id = 1");
    $company_settings = $company_stmt->fetch();

    $stmt = $pdo->prepare("
        SELECT o.*, t.table_number, u.name as user_name 
        FROM orders o 
        LEFT JOIN tables t ON o.table_id = t.id
        JOIN users u ON o.user_id = u.id
        WHERE o.id = ?
    ");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();

    if (!$order) {
        die('Order not found.');
    }

    $item_stmt = $pdo->prepare("
        SELECT oi.*, mi.name as item_name 
        FROM order_items oi
        JOIN menu_items mi ON oi.menu_item_id = mi.id
        WHERE oi.order_id = ?
    ");
    $item_stmt->execute([$order_id]);
    $order_items = $item_stmt->fetchAll();

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Receipt - Order #<?php echo $order['id']; ?></title>
    <style>
        body {
            font-family: 'Courier New', Courier, monospace;
            width: 300px;
            margin: 0 auto;
            font-size: 14px;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        h1 {
            font-size: 20px;
            margin: 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 5px 0;
        }
        .items th, .items td {
            border-bottom: 1px dashed #000;
        }
        .items td:nth-child(1) {
            text-align: left;
        }
        .items td:nth-child(2), .items td:nth-child(3), .items td:nth-child(4) {
            text-align: right;
        }
        .totals {
            margin-top: 10px;
        }
        .totals td:first-child {
            text-align: right;
            font-weight: bold;
        }
        .totals td:last-child {
            text-align: right;
        }
        .footer {
            text-align: center;
            margin-top: 20px;
        }
        @media print {
            body {
                width: 100%;
            }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="header">
        <?php if ($company_settings && !empty($company_settings['logo'])): ?>
            <img src="../assets/images/<?php echo htmlspecialchars($company_settings['logo']); ?>" alt="Company Logo" style="max-height: 80px; margin-bottom: 10px;">
        <?php endif; ?>
        <h1><?php echo htmlspecialchars($company_settings['name'] ?? 'Matosh POS'); ?></h1>
        <p><?php echo nl2br(htmlspecialchars($company_settings['address'] ?? '')); ?></p>
        <p><?php echo htmlspecialchars($company_settings['phone'] ?? ''); ?></p>
        <p><?php echo htmlspecialchars($company_settings['email'] ?? ''); ?></p>
        <hr>
        <p>Date: <?php echo date('M d, Y H:i', strtotime($order['created_at'])); ?></p>
        <p>Receipt #: <?php echo $order['id']; ?></p>
        <p>Cashier: <?php echo htmlspecialchars($order['user_name']); ?></p>
    </div>

    <table class="items">
        <thead>
            <tr>
                <th>Item</th>
                <th>Qty</th>
                <th>Price</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($order_items as $item): ?>
            <tr>
                <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                <td><?php echo $item['quantity']; ?></td>
                <td><?php echo number_format($item['price'], 2); ?></td>
                <td><?php echo number_format($item['subtotal'], 2); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <table class="totals">
        <tbody>
            <tr>
                <td>Subtotal:</td>
                <td><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($order['subtotal'], 2); ?></td>
            </tr>
            <tr>
                <td>Tax:</td>
                <td><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($order['tax_amount'], 2); ?></td>
            </tr>
            <tr>
                <td>Total:</td>
                <td><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($order['total_price'], 2); ?></td>
            </tr>
        </tbody>
    </table>

    <div class="footer">
        <p>Thank you for your visit!</p>
    </div>
</body>
</html>
