<?php
require_once '../config/db.php';

try {
    $stmt = $pdo->query("
        SELECT o.id, o.created_at, o.total_price, o.payment_status, t.table_number, u.name as user_name
        FROM orders o
        LEFT JOIN tables t ON o.table_id = t.id
        JOIN users u ON o.user_id = u.id
        ORDER BY o.created_at DESC
    ");
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="orders.csv"');

    $output = fopen('php://output', 'w');

    // Add CSV header
    fputcsv($output, ['Order ID', 'Date', 'Total', 'Payment Status', 'Table', 'Cashier']);

    // Add data rows
    foreach ($orders as $order) {
        fputcsv($output, [
            $order['id'],
            $order['created_at'],
            $order['total_price'],
            $order['payment_status'],
            $order['table_number'],
            $order['user_name'],
        ]);
    }

    fclose($output);
    exit;

} catch (PDOException $e) {
    die("Could not export orders: " . $e->getMessage());
}
