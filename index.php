<?php
require_once 'includes/auth.php';
require_once 'config/db.php';
require_once 'includes/header.php';

// Fetch orders for the Kanban board
try {
    $stmt = $pdo->query("
        SELECT 
            o.id, 
            o.status, 
            o.payment_status, 
            o.total_price,
            t.table_number,
            o.table_id,
            GROUP_CONCAT(mi.name SEPARATOR ', ') as items
        FROM orders o
        LEFT JOIN tables t ON o.table_id = t.id
        LEFT JOIN order_items oi ON o.id = oi.order_id
        LEFT JOIN menu_items mi ON oi.menu_item_id = mi.id
        WHERE o.status IN ('Pending', 'In Progress')
        GROUP BY o.id
        ORDER BY o.created_at DESC
    ");
    $orders = $stmt->fetchAll();
} catch (PDOException $e) {
    // In a real app, you'd log this error and show a user-friendly message.
    die("Could not fetch orders: " . $e->getMessage());
}

// Group orders by status
$kanban_columns = [
    'Pending' => [],
    'In Progress' => [],
];

foreach ($orders as $order) {
    if (array_key_exists($order['status'], $kanban_columns)) {
        $kanban_columns[$order['status']][] = $order;
    }
}
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h2">Order Dashboard</h1>
        <a href="/pages/orders.php?action=create" class="btn btn-primary">Create New Order</a>
    </div>

    <div class="kanban-board">
        <?php foreach ($kanban_columns as $status => $orders_in_column): ?>
            <div class="kanban-column">
                <h5 class="kanban-column-title"><?php echo htmlspecialchars($status); ?> (<?php echo count($orders_in_column); ?>)</h5>
                <div class="kanban-cards">
                    <?php if (empty($orders_in_column)): ?>
                        <div class="text-center text-muted p-3">No orders</div>
                    <?php else: ?>
                        <?php foreach ($orders_in_column as $order): ?>
                            <div class="card kanban-card">
                                <div class="card-body p-3">
                                    <h6 class="card-title"><?php echo htmlspecialchars($order['table_number'] ?? 'Takeaway'); ?></h6>
                                    <p class="card-text text-muted small"><?php echo htmlspecialchars($order['items'] ?? 'No items'); ?></p>
                                    <div class="d-flex justify-content-between align-items-center mt-3">
                                        <span class="fw-bold text-primary"><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($order['total_price'], 2); ?></span>
                                        <span class="badge <?php echo $order['payment_status'] === 'Paid' ? 'bg-success' : 'bg-warning'; ?>">
                                            <?php echo htmlspecialchars($order['payment_status']); ?>
                                        </span>
                                    </div>
                                    <div class="mt-3">
                                        <a href="/pages/orders.php?action=view&id=<?php echo $order['id']; ?>" class="btn btn-sm btn-outline-primary">Details</a>
                                        <?php if ($order['payment_status'] === 'Paid' && $order['table_id']): ?>
                                            <a href="/api/free_table.php?table_id=<?php echo $order['table_id']; ?>&order_id=<?php echo $order['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to free this table?');">Free Table</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php
require_once 'includes/footer.php';
?>
