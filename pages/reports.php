<?php
require_once '../includes/auth.php';
require_once '../config/db.php';

// Check for Admin/Cashier role
if (!has_any_role(['Admin', 'Cashier'])) {
    die('Access Denied: You do not have permission to view reports.');
}

try {
    // Daily Sales
    $stmt = $pdo->prepare("SELECT SUM(total_price) as total_sales FROM orders WHERE DATE(created_at) = CURDATE() AND payment_status = 'Paid'");
    $stmt->execute();
    $daily_sales = $stmt->fetchColumn() ?? 0;

    // Ongoing Unpaid Orders
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE payment_status = 'Unpaid'");
    $stmt->execute();
    $unpaid_orders = $stmt->fetchColumn() ?? 0;

    // Best-selling Items
    $stmt = $pdo->prepare("
        SELECT mi.name, SUM(oi.quantity) as total_quantity
        FROM order_items oi
        JOIN menu_items mi ON oi.menu_item_id = mi.id
        GROUP BY mi.name
        ORDER BY total_quantity DESC
        LIMIT 5
    ");
    $stmt->execute();
    $best_selling_items = $stmt->fetchAll();

    // Pagination for Order History
    $page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 15;
    $offset = ($page - 1) * $limit;

    // Get total number of orders
    $total_orders_stmt = $pdo->query("SELECT COUNT(*) FROM orders");
    $total_orders = $total_orders_stmt->fetchColumn();
    $total_pages = ceil($total_orders / $limit);

    // Fetch orders for the current page
    $orders_stmt = $pdo->prepare("
        SELECT o.*, t.table_number, u.name as user_name
        FROM orders o
        LEFT JOIN tables t ON o.table_id = t.id
        JOIN users u ON o.user_id = u.id
        ORDER BY o.created_at DESC
        LIMIT :limit OFFSET :offset
    ");
    $orders_stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $orders_stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $orders_stmt->execute();
    $paginated_orders = $orders_stmt->fetchAll();

} catch (PDOException $e) {
    $error = "Database Error: " . $e->getMessage();
}

require_once '../includes/header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h2">Reports & Analytics</h1>
        <div>
            <a href="/api/export_csv.php" class="btn btn-outline-secondary">Export to CSV</a>
            <button class="btn btn-outline-secondary">Export to PDF</button>
        </div>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php else: ?>
        <!-- Dashboard Cards -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title text-muted">Today's Sales</h5>
                        <p class="card-text h2"><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($daily_sales, 2); ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title text-muted">Ongoing Unpaid Orders</h5>
                        <p class="card-text h2"><?php echo $unpaid_orders; ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Best-selling Items & Order History -->
        <div class="row">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">Best-Selling Items</div>
                    <div class="card-body">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Item Name</th>
                                    <th>Total Sold</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($best_selling_items as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['name']); ?></td>
                                        <td><?php echo $item['total_quantity']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">Order History</div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Date</th>
                                    <th>Total</th>
                                    <th>Payment</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($paginated_orders as $order): ?>
                                    <tr>
                                        <td>#<?php echo $order['id']; ?></td>
                                        <td><?php echo date('Y-m-d H:i', strtotime($order['created_at'])); ?></td>
                                        <td><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($order['total_price'], 2); ?></td>
                                        <td><span class="badge <?php echo $order['payment_status'] === 'Paid' ? 'bg-success' : 'bg-warning'; ?>"><?php echo htmlspecialchars($order['payment_status']); ?></span></td>
                                        <td><a href="/pages/orders.php?action=view&id=<?php echo $order['id']; ?>" class="btn btn-sm btn-outline-primary">View</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer">
                        <nav aria-label="Page navigation">
                            <ul class="pagination justify-content-center mb-0">
                                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>">Previous</a>
                                </li>
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                </li>
                                <?php endfor; ?>
                                <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>">Next</a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
