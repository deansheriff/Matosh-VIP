<?php
require_once '../includes/auth.php';
require_once '../config/db.php';

// Ensure user is logged in before processing any actions
if (!isset($_SESSION['user_id'])) {
    // You can redirect or show a generic error.
    // For API-like behavior, a JSON response might be better.
    die('Authentication required. Please log in again.');
}

$action = $_GET['action'] ?? 'list';
$order_id = $_GET['id'] ?? null;
$error = '';
$success = '';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'process_payment') {
        $order_id_payment = $_POST['order_id'];
        $amount = $_POST['amount'];
        $payment_type = $_POST['payment_type'];

        if (!empty($order_id_payment) && !empty($amount) && !empty($payment_type)) {
            $pdo->beginTransaction();

            // 1. Record the payment
            $stmt = $pdo->prepare("INSERT INTO payments (order_id, amount, payment_type) VALUES (?, ?, ?)");
            $stmt->execute([$order_id_payment, $amount, $payment_type]);

            // 2. Get order total and current paid amount
            $stmt = $pdo->prepare("SELECT total_price, table_id FROM orders WHERE id = ?");
            $stmt->execute([$order_id_payment]);
            $order_details = $stmt->fetch();
            $total_price = $order_details['total_price'];
            $table_id = $order_details['table_id'];

            $stmt = $pdo->prepare("SELECT SUM(amount) FROM payments WHERE order_id = ?");
            $stmt->execute([$order_id_payment]);
            $total_paid = $stmt->fetchColumn();

            // 3. Determine and update payment status
            $new_payment_status = 'Partially Paid';
            if ($total_paid >= $total_price) {
                $new_payment_status = 'Paid';
                // 4. If fully paid, update table status to available
                if ($table_id) {
                    $update_table_stmt = $pdo->prepare("UPDATE tables SET status = 'available' WHERE id = ?");
                    $update_table_stmt->execute([$table_id]);
                }
            }
            
            $update_order_stmt = $pdo->prepare("UPDATE orders SET payment_status = ? WHERE id = ?");
            $update_order_stmt->execute([$new_payment_status, $order_id_payment]);

            // Update order status to In Progress if it was Pending
            $stmt = $pdo->prepare("UPDATE orders SET status = 'In Progress' WHERE id = ? AND status = 'Pending'");
            $stmt->execute([$order_id_payment]);

            $pdo->commit();
            header("Location: orders.php?action=view&id=$order_id_payment&success=Payment+processed");
            exit;
        } else {
            $error = "Payment amount and type are required.";
        }

    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'delete') {
        if (has_role('Admin')) {
            $order_id_to_delete = $_POST['id'];
            $pdo->beginTransaction();
            try {
                // Delete payments
                $stmt = $pdo->prepare("DELETE FROM payments WHERE order_id = ?");
                $stmt->execute([$order_id_to_delete]);

                // Delete order items
                $stmt = $pdo->prepare("DELETE FROM order_items WHERE order_id = ?");
                $stmt->execute([$order_id_to_delete]);

                // Delete order
                $stmt = $pdo->prepare("DELETE FROM orders WHERE id = ?");
                $stmt->execute([$order_id_to_delete]);

                $pdo->commit();
                header("Location: orders.php?success=Order+deleted+successfully");
                exit;
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = "Error deleting order: " . $e->getMessage();
            }
        } else {
            $error = "You do not have permission to delete orders.";
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Simplified POST handling for now.
        // A real app would have more complex logic for creating/updating orders.
        $table_id = $_POST['table_id'];
        $customer_id = $_POST['customer_id'] ?: null;
        $menu_items = $_POST['menu_items'];
        $user_id = $_SESSION['user_id'];

        if (!empty($table_id) && !empty($menu_items)) {
            $pdo->beginTransaction();

            // Fetch active tax
            $tax_stmt = $pdo->query("SELECT * FROM tax_settings WHERE is_active = 1");
            $active_tax = $tax_stmt->fetch();

            // Create order
            $subtotal_price = 0;
            $tax_amount = 0;
            $total_price = 0;
            
            // Calculate subtotal from items
            $price_stmt = $pdo->prepare("SELECT price FROM menu_items WHERE id = ?");
            foreach ($menu_items as $item_id => $quantity) {
                if ($quantity > 0) {
                    $price_stmt->execute([$item_id]);
                    $price = $price_stmt->fetchColumn();
                    $subtotal_price += $price * $quantity;
                }
            }

            // Calculate tax
            if ($active_tax) {
                if ($active_tax['tax_type'] === 'percentage') {
                    $tax_amount = ($subtotal_price * $active_tax['tax_rate']) / 100;
                } else {
                    $tax_amount = $active_tax['tax_rate'];
                }
            }

            // Calculate total
            $total_price = $subtotal_price + $tax_amount;

            $stmt = $pdo->prepare("INSERT INTO orders (table_id, customer_id, user_id, subtotal, tax_amount, total_price) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$table_id, $customer_id, $user_id, $subtotal_price, $tax_amount, $total_price]);
            $new_order_id = $pdo->lastInsertId();

            // Add order items
            $item_stmt = $pdo->prepare("INSERT INTO order_items (order_id, menu_item_id, quantity, price, subtotal) VALUES (?, ?, ?, ?, ?)");
            
            foreach ($menu_items as $item_id => $quantity) {
                if ($quantity > 0) {
                    // We already have the price from the subtotal calculation, but for robustness let's re-fetch
                    $price_stmt->execute([$item_id]);
                    $price = $price_stmt->fetchColumn();
                    $item_subtotal = $price * $quantity;
                    $item_stmt->execute([$new_order_id, $item_id, $quantity, $price, $item_subtotal]);
                }
            }

            
            // Update table status
            $table_status_stmt = $pdo->prepare("UPDATE tables SET status = 'occupied' WHERE id = ?");
            $table_status_stmt->execute([$table_id]);

            $pdo->commit();
            header("Location: orders.php?action=view&id=$new_order_id&success=Order+created");
            exit;
        } else {
            $error = "Please select a table and at least one menu item.";
        }
    }

    // Fetch data for the page
    $tables = $pdo->query("SELECT * FROM tables WHERE status = 'available'")->fetchAll();
    $menu_items_all = $pdo->query("SELECT * FROM menu_items WHERE availability = 1")->fetchAll();
    $customers = $pdo->query("SELECT * FROM customers ORDER BY name")->fetchAll();
    
    if ($action === 'view' && $order_id) {
        $stmt = $pdo->prepare("
            SELECT o.*, t.table_number, u.name as user_name, c.name as customer_name
            FROM orders o 
            LEFT JOIN tables t ON o.table_id = t.id
            LEFT JOIN customers c ON o.customer_id = c.id
            JOIN users u ON o.user_id = u.id
            WHERE o.id = ?
        ");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch();

        if ($order) {
            $item_stmt = $pdo->prepare("
                SELECT oi.*, mi.name as item_name 
                FROM order_items oi
                JOIN menu_items mi ON oi.menu_item_id = mi.id
                WHERE oi.order_id = ?
            ");
            $item_stmt->execute([$order_id]);
            $order_items = $item_stmt->fetchAll();

            // Fetch payments made for this order
            $payment_stmt = $pdo->prepare("SELECT SUM(amount) as total_paid FROM payments WHERE order_id = ?");
            $payment_stmt->execute([$order_id]);
            $total_paid = $payment_stmt->fetchColumn() ?? 0;
        }
    } else {
        $orders = $pdo->query("SELECT o.*, t.table_number FROM orders o LEFT JOIN tables t ON o.table_id = t.id ORDER BY o.created_at DESC LIMIT 20")->fetchAll();
    }

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $error = "Database Error: " . $e->getMessage();
}

$success = $_GET['success'] ?? '';

require_once '../includes/header.php';
?>

<div class="container">
    <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

    <?php if ($action === 'list'): ?>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h2">All Orders</h1>
            <a href="orders.php?action=create" class="btn btn-primary">Create New Order</a>
        </div>
        <div class="card">
            <div class="card-body">
                <table class="table">
                    <thead><tr><th>ID</th><th>Table</th><th>Total</th><th>Status</th><th>Payment</th><th>Date</th><th></th></tr></thead>
                    <tbody>
                        <?php foreach($orders as $ord): ?>
                        <tr>
                            <td>#<?php echo $ord['id']; ?></td>
                            <td><?php echo htmlspecialchars($ord['table_number'] ?? 'N/A'); ?></td>
                            <td><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($ord['total_price'], 2); ?></td>
                            <td><span class="badge bg-info"><?php echo htmlspecialchars($ord['status']); ?></span></td>
                            <td><span class="badge <?php echo $ord['payment_status'] === 'Paid' ? 'bg-success' : 'bg-warning'; ?>"><?php echo htmlspecialchars($ord['payment_status']); ?></span></td>
                            <td><?php echo date('M d, Y H:i', strtotime($ord['created_at'])); ?></td>
                            <td>
                                <a href="?action=view&id=<?php echo $ord['id']; ?>" class="btn btn-sm btn-outline-primary">View</a>
                                <?php if (has_role('Admin')): ?>
                                <form action="orders.php" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this order?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $ord['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php elseif ($action === 'create'): ?>
        <?php
        // Fetch active tax to pass to JavaScript
        $tax_stmt = $pdo->query("SELECT tax_rate, tax_type FROM tax_settings WHERE is_active = 1");
        $active_tax_js = $tax_stmt->fetch();
        $tax_rate_js = $active_tax_js['tax_rate'] ?? 0;
        $tax_type_js = $active_tax_js['tax_type'] ?? 'percentage';
        ?>
        <h1 class="h2 mb-4">Create New Order</h1>
        <form action="orders.php" method="POST" id="create-order-form" 
              data-currency-symbol="<?php echo CURRENCY_SYMBOL; ?>"
              data-tax-rate="<?php echo $tax_rate_js; ?>"
              data-tax-type="<?php echo $tax_type_js; ?>">
            <div class="row">
                <!-- Left Column: Menu -->
                <div class="col-md-7">
                    <div class="card">
                        <div class="card-header">
                            <input type="text" id="menu-search" class="form-control" placeholder="Search menu items...">
                        </div>
                        <div class="card-body" style="max-height: 60vh; overflow-y: auto;">
                            <div id="menu-items-container" class="row">
                                <?php foreach($menu_items_all as $item): ?>
                                    <div class="col-md-4 col-lg-3 mb-3 menu-item-card" 
                                         data-id="<?php echo $item['id']; ?>" 
                                         data-name="<?php echo htmlspecialchars($item['name']); ?>" 
                                         data-price="<?php echo $item['price']; ?>"
                                         style="cursor: pointer;">
                                        <div class="card h-100">
                                            <?php if ($item['image']): ?>
                                                <img src="../assets/images/products/<?php echo htmlspecialchars($item['image']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($item['name']); ?>" style="height: 120px; object-fit: cover;">
                                            <?php else: ?>
                                                <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height: 120px;">
                                                    <span class="text-muted">No Image</span>
                                                </div>
                                            <?php endif; ?>
                                            <div class="card-body d-flex flex-column">
                                                <h6 class="card-title flex-grow-1"><?php echo htmlspecialchars($item['name']); ?></h6>
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
                        <div class="card-header">
                            Current Order
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="table_id" class="form-label">Table</label>
                                <select name="table_id" id="table_id" class="form-select" required>
                                    <option value="">Select a table...</option>
                                    <?php foreach($tables as $table): ?>
                                    <option value="<?php echo $table['id']; ?>"><?php echo htmlspecialchars($table['table_number']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="customer_id" class="form-label">Customer</label>
                                <div class="input-group">
                                    <select name="customer_id" id="customer_id" class="form-select">
                                        <option value="">Select a customer (optional)</option>
                                        <?php foreach($customers as $customer): ?>
                                        <option value="<?php echo $customer['id']; ?>"><?php echo htmlspecialchars($customer['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button class="btn btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#addCustomerModal">Add</button>
                                </div>
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
                                <tbody id="order-items-tbody">
                                    <!-- Order items will be injected here by JavaScript -->
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th colspan="2" class="text-end">Subtotal:</th>
                                        <th id="order-subtotal"><?php echo CURRENCY_SYMBOL; ?>0.00</th>
                                        <th></th>
                                    </tr>
                                    <tr>
                                        <th colspan="2" class="text-end">Tax:</th>
                                        <th id="order-tax"><?php echo CURRENCY_SYMBOL; ?>0.00</th>
                                        <th></th>
                                    </tr>
                                    <tr class="fw-bold">
                                        <th colspan="2" class="text-end">Total:</th>
                                        <th id="order-total"><?php echo CURRENCY_SYMBOL; ?>0.00</th>
                                        <th></th>
                                    </tr>
                                </tfoot>
                            </table>
                            <div id="hidden-form-inputs"></div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">Submit Order</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
        <script src="/assets/js/order.js"></script>
<script src="/assets/js/customer.js"></script>

<!-- Add Customer Modal -->
<div class="modal fade" id="addCustomerModal" tabindex="-1" aria-labelledby="addCustomerModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="add-customer-form">
                <div class="modal-header">
                    <h5 class="modal-title" id="addCustomerModalLabel">Add New Customer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="customer-name" class="form-label">Name</label>
                        <input type="text" class="form-control" id="customer-name" required>
                    </div>
                    <div class="mb-3">
                        <label for="customer-phone" class="form-label">Phone</label>
                        <input type="text" class="form-control" id="customer-phone">
                    </div>
                    <div class="mb-3">
                        <label for="customer-email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="customer-email">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Customer</button>
                </div>
            </form>
        </div>
    </div>
</div>



    <?php elseif ($action === 'view' && isset($order)): ?>
        <h1 class="h2 mb-4">Order #<?php echo $order['id']; ?></h1>
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        Order Items
                        <a href="modify_order.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-info">Modify Order</a>
                    </div>
                    <div class="card-body">
                        <table class="table">
                            <thead><tr><th>Item</th><th>Quantity</th><th>Price</th><th>Subtotal</th></tr></thead>
                            <tbody>
                                <?php foreach($order_items as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                    <td><?php echo $item['quantity']; ?></td>
                                    <td><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($item['price'], 2); ?></td>
                                    <td><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($item['subtotal'], 2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="3" class="text-end">Subtotal:</th>
                                    <th><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($order['subtotal'], 2); ?></th>
                                </tr>
                                <tr>
                                    <th colspan="3" class="text-end">Tax:</th>
                                    <th><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($order['tax_amount'], 2); ?></th>
                                </tr>
                                <tr class="fw-bold">
                                    <th colspan="3" class="text-end">Total:</th>
                                    <th><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($order['total_price'], 2); ?></th>
                                </tr>
                                <tr class="text-success">
                                    <th colspan="3" class="text-end">Paid:</th>
                                    <th><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($total_paid, 2); ?></th>
                                </tr>
                                <tr class="fw-bold">
                                    <th colspan="3" class="text-end">Amount Due:</th>
                                    <th><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($order['total_price'] - $total_paid, 2); ?></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">Details</div>
                    <div class="card-body">
                        <p><strong>Table:</strong> <?php echo htmlspecialchars($order['table_number'] ?? 'N/A'); ?></p>
                        <p><strong>Customer:</strong> <?php echo htmlspecialchars($order['customer_name'] ?? 'N/A'); ?></p>
                        <p><strong>Status:</strong> <?php echo htmlspecialchars($order['status']); ?></p>
                        <p><strong>Payment:</strong> <span class="badge <?php echo $order['payment_status'] === 'Paid' ? 'bg-success' : ($order['payment_status'] === 'Partially Paid' ? 'bg-primary' : 'bg-warning'); ?>"><?php echo htmlspecialchars($order['payment_status']); ?></span></p>
                        <p><strong>Cashier:</strong> <?php echo htmlspecialchars($order['user_name']); ?></p>
                        <p><strong>Time:</strong> <?php echo date('M d, Y H:i', strtotime($order['created_at'])); ?></p>
                        <hr>
                        <div class="d-grid gap-2">
                            <?php if($order['payment_status'] !== 'Paid'): ?>
                            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#paymentModal">Process Payment</button>
                            <?php endif; ?>
                            <a href="/pages/receipt.php?id=<?php echo $order['id']; ?>" class="btn btn-secondary" target="_blank">Print Receipt</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-warning">Order not found or invalid action.</div>
    <?php endif; ?>
</div>

<!-- Payment Modal -->
<?php if ($action === 'view' && isset($order)): ?>
<div class="modal fade" id="paymentModal" tabindex="-1" aria-labelledby="paymentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="orders.php" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="paymentModalLabel">Process Payment for Order #<?php echo $order['id']; ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="process_payment">
                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                    
                    <div class="mb-3">
                        <label for="amount" class="form-label">Amount</label>
                        <input type="number" step="0.01" class="form-control" id="amount" name="amount" value="<?php echo number_format($order['total_price'] - $total_paid, 2, '.', ''); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="payment_type" class="form-label">Payment Type</label>
                        <select class="form-select" id="payment_type" name="payment_type" required>
                            <option value="cash">Cash</option>
                            <option value="card">Card</option>
                            <option value="transfer">Transfer</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Submit Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
