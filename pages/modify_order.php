<?php
require_once '../includes/auth.php';
require_once '../config/db.php';

$order_id = $_GET['id'] ?? null;
$error = '';

if (!$order_id) {
    header("Location: orders.php?error=No+order+ID");
    exit;
}

try {
    // Handle form submission for updating the order
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $table_id = $_POST['table_id'];
        $customer_id = $_POST['customer_id'] ?: null;

        $pdo->beginTransaction();

        // 1. Delete existing order items
        $delete_stmt = $pdo->prepare("DELETE FROM order_items WHERE order_id = ?");
        $delete_stmt->execute([$order_id]);

        // 2. Insert new/updated order items and calculate total price
        $new_total_price = 0;
        $item_stmt = $pdo->prepare("INSERT INTO order_items (order_id, menu_item_id, quantity, price, subtotal) VALUES (?, ?, ?, ?, ?)");
        $price_stmt = $pdo->prepare("SELECT price FROM menu_items WHERE id = ?");

        foreach ($_POST['menu_items'] as $item_id_post => $quantity) {
            if ($quantity > 0) {
                $price_stmt->execute([$item_id_post]);
                $price = $price_stmt->fetchColumn();
                $subtotal = $price * $quantity;
                $new_total_price += $subtotal;
                $item_stmt->execute([$order_id, $item_id_post, $quantity, $price, $subtotal]);
            }
        }

        // 3. Update the order table
        $update_stmt = $pdo->prepare("UPDATE orders SET total_price = ?, table_id = ?, customer_id = ? WHERE id = ?");
        $update_stmt->execute([$new_total_price, $table_id, $customer_id, $order_id]);

        $pdo->commit();
        header("Location: orders.php?action=view&id=$order_id&success=Order+modified+successfully");
        exit;
    }

    // Fetch data for page display
    $order = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $order->execute([$order_id]);
    $order = $order->fetch();

    if (!$order) {
        throw new Exception("Order not found.");
    }

    $all_menu_items = $pdo->query("SELECT * FROM menu_items WHERE availability = 1")->fetchAll();
    $tables = $pdo->query("SELECT * FROM tables")->fetchAll(); // Fetch all tables for dropdown
    $customers = $pdo->query("SELECT * FROM customers ORDER BY name")->fetchAll();

    // Fetch current order items to pass to JavaScript
    $current_items_stmt = $pdo->prepare("SELECT mi.id, mi.name, mi.price, oi.quantity FROM order_items oi JOIN menu_items mi ON oi.menu_item_id = mi.id WHERE oi.order_id = ?");
    $current_items_stmt->execute([$order_id]);
    $initial_order_items = $current_items_stmt->fetchAll();

    $initial_order_js = [];
    foreach ($initial_order_items as $item) {
        $initial_order_js[$item['id']] = [
            'name' => $item['name'],
            'price' => (float)$item['price'],
            'quantity' => (int)$item['quantity']
        ];
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $error = "Error: " . $e->getMessage();
}

require_once '../includes/header.php';
?>

<div class="container">
    <h1 class="h2 mb-4">Modify Order #<?php echo htmlspecialchars($order_id); ?></h1>

    <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <form action="modify_order.php?id=<?php echo htmlspecialchars($order_id); ?>" method="POST" id="modify-order-form" data-currency-symbol="<?php echo CURRENCY_SYMBOL; ?>">
        <div class="row">
            <!-- Left Column: Menu -->
            <div class="col-md-7">
                <div class="card">
                    <div class="card-header">
                        <input type="text" id="menu-search" class="form-control" placeholder="Search menu items...">
                    </div>
                    <div class="card-body" style="max-height: 60vh; overflow-y: auto;">
                        <div id="menu-items-container" class="row">
                            <?php foreach($all_menu_items as $item): ?>
                                <div class="col-md-6 mb-3 menu-item-card" 
                                        data-id="<?php echo $item['id']; ?>" 
                                        data-name="<?php echo htmlspecialchars($item['name']); ?>" 
                                        data-price="<?php echo $item['price']; ?>"
                                        style="cursor: pointer;">
                                    <div class="card h-100">
                                        <div class="card-body">
                                            <h6 class="card-title"><?php echo htmlspecialchars($item['name']); ?></h6>
                                            <p class="card-text text-muted mb-0"><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($item['price'], 2); ?></p>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Current Order -->
            <div class="col-md-5">
                <div class="card">
                    <div class="card-header">Current Order</div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="table_id" class="form-label">Table</label>
                            <select name="table_id" id="table_id" class="form-select" required>
                                <?php foreach($tables as $table): ?>
                                <option value="<?php echo $table['id']; ?>" <?php echo ($order['table_id'] == $table['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($table['table_number']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="customer_id" class="form-label">Customer</label>
                            <select name="customer_id" id="customer_id" class="form-select">
                                <option value="">Select a customer (optional)</option>
                                <?php foreach($customers as $customer): ?>
                                <option value="<?php echo $customer['id']; ?>" <?php echo ($order['customer_id'] == $customer['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($customer['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <hr>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Qty</th>
                                    <th>Subtotal</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="order-items-tbody"></tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="2" class="text-end">Total:</th>
                                    <th id="order-total"><?php echo CURRENCY_SYMBOL; ?>0.00</th>
                                    <th></th>
                                </tr>
                            </tfoot>
                        </table>
                        <div id="hidden-form-inputs"></div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Update Order</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Data for JS initialization -->
<script id="initial-order-data" type="application/json">
    <?php echo json_encode($initial_order_js, JSON_NUMERIC_CHECK); ?>
</script>

<script src="/assets/js/order.js"></script>

<?php require_once '../includes/footer.php'; ?>
