<?php
require_once '../config/db.php';

$table_id = $_GET['table_id'] ?? null;
$order_id = $_GET['order_id'] ?? null;

if ($table_id && $order_id) {
    try {
        $pdo->beginTransaction();

        // Update order status to Completed
        $order_stmt = $pdo->prepare("UPDATE orders SET status = 'Completed' WHERE id = ?");
        $order_stmt->execute([$order_id]);

        // Update table status to available
        $table_stmt = $pdo->prepare("UPDATE tables SET status = 'available' WHERE id = ?");
        $table_stmt->execute([$table_id]);

        $pdo->commit();
        header('Location: /index.php?success=Table+freed+and+order+completed');
    } catch (PDOException $e) {
        $pdo->rollBack();
        header('Location: /index.php?error=Database+error');
    }
} else {
    header('Location: /index.php?error=Invalid+IDs');
}
exit;
