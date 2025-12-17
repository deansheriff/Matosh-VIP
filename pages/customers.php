<?php
require_once '../includes/auth.php';
require_once '../config/db.php';

// Check for Admin/Cashier role
if (!has_any_role(['Admin', 'Cashier'])) {
    die('Access Denied: You do not have permission to manage customers.');
}

$action = $_POST['action'] ?? $_GET['action'] ?? 'list';
$customer_id = $_REQUEST['id'] ?? null;

$name = '';
$phone = '';
$email = '';
$error = '';
$success = '';

try {
    // Handle POST requests for CUD operations
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = $_POST['name'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $email = $_POST['email'] ?? '';

        if ($action === 'create' || $action === 'update') {
            if (empty($name)) {
                $error = 'Customer name is required.';
            } else {
                if ($action === 'create') {
                    $stmt = $pdo->prepare("INSERT INTO customers (name, phone, email) VALUES (?, ?, ?)");
                    $stmt->execute([$name, $phone, $email]);
                    $success = 'Customer created successfully.';
                } elseif ($action === 'update' && $customer_id) {
                    $stmt = $pdo->prepare("UPDATE customers SET name = ?, phone = ?, email = ? WHERE id = ?");
                    $stmt->execute([$name, $phone, $email, $customer_id]);
                    $success = 'Customer updated successfully.';
                }
                header("Location: customers.php?success=" . urlencode($success));
                exit;
            }
        } elseif ($action === 'delete' && $customer_id) {
            // Deleting a customer will set their ID to NULL in the orders table due to the constraint
            $stmt = $pdo->prepare("DELETE FROM customers WHERE id = ?");
            $stmt->execute([$customer_id]);
            $success = 'Customer deleted successfully.';
            header("Location: customers.php?success=" . urlencode($success));
            exit;
        }
    }

    // Handle GET request for editing a customer
    if ($action === 'edit' && $customer_id) {
        $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
        $stmt->execute([$customer_id]);
        $customer = $stmt->fetch();
        if ($customer) {
            $name = $customer['name'];
            $phone = $customer['phone'];
            $email = $customer['email'];
        }
    }

    // Fetch all customers for display
    $customers = $pdo->query("SELECT * FROM customers ORDER BY name")->fetchAll();

} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? $error;

require_once '../includes/header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h2">Customer Management</h1>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#customerModal">Add New Customer</button>
    </div>

    <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

    <div class="card">
        <div class="card-body">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($customers as $customer): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($customer['name']); ?></td>
                            <td><?php echo htmlspecialchars($customer['phone'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($customer['email'] ?? 'N/A'); ?></td>
                            <td>
                                <a href="customers.php?action=edit&id=<?php echo $customer['id']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                <form action="customers.php" method="POST" class="d-inline" onsubmit="return confirm('Are you sure? Deleting a customer cannot be undone.');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $customer['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="customerModal" tabindex="-1" aria-labelledby="customerModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="customers.php" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="customerModalLabel"><?php echo ($action === 'edit') ? 'Edit' : 'Add'; ?> Customer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="<?php echo ($action === 'edit' && $customer_id) ? 'update' : 'create'; ?>">
                    <?php if ($action === 'edit' && $customer_id): ?>
                        <input type="hidden" name="id" value="<?php echo $customer_id; ?>">
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="name" class="form-label">Customer Name</label>
                        <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($name); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="phone" class="form-label">Phone Number</label>
                        <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($phone); ?>">
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
if ($action === 'edit' && $customer_id):
?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var editModal = new bootstrap.Modal(document.getElementById('customerModal'));
        editModal.show();
    });
</script>
<?php
endif;

require_once '../includes/footer.php';
?>
