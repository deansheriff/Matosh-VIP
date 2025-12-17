<?php
require_once '../includes/auth.php';
require_once '../config/db.php';

// Check for Admin/Cashier role
if (!has_any_role(['Admin', 'Cashier'])) {
    die('Access Denied: You do not have permission to manage tables.');
}

$action = $_POST['action'] ?? $_GET['action'] ?? 'list';
$table_id = $_REQUEST['id'] ?? null;

$table_number = '';
$status = 'available';
$error = '';
$success = '';

try {
    // Handle POST requests for CUD operations
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $table_number = $_POST['table_number'] ?? '';
        $status = $_POST['status'] ?? 'available';

        if ($action === 'create' || $action === 'update') {
            if (empty($table_number)) {
                $error = 'Table number/name is required.';
            } else {
                if ($action === 'create') {
                    $stmt = $pdo->prepare("INSERT INTO tables (table_number, status) VALUES (?, ?)");
                    $stmt->execute([$table_number, $status]);
                    $success = 'Table created successfully.';
                } elseif ($action === 'update' && $table_id) {
                    $stmt = $pdo->prepare("UPDATE tables SET table_number = ?, status = ? WHERE id = ?");
                    $stmt->execute([$table_number, $status, $table_id]);
                    $success = 'Table updated successfully.';
                }
                header("Location: tables.php?success=" . urlencode($success));
                exit;
            }
        } elseif ($action === 'delete' && $table_id) {
            // Check if table is in use by any order
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE table_id = ?");
            $stmt->execute([$table_id]);
            if ($stmt->fetchColumn() > 0) {
                $error = 'Cannot delete table: It is associated with existing orders.';
            } else {
                $stmt = $pdo->prepare("DELETE FROM tables WHERE id = ?");
                $stmt->execute([$table_id]);
                $success = 'Table deleted successfully.';
            }
            $location = "tables.php?" . ($error ? "error=" . urlencode($error) : "success=" . urlencode($success));
            header("Location: $location");
            exit;
        }
    }

    // Handle GET request for editing a table
    if ($action === 'edit' && $table_id) {
        $stmt = $pdo->prepare("SELECT * FROM tables WHERE id = ?");
        $stmt->execute([$table_id]);
        $table = $stmt->fetch();
        if ($table) {
            $table_number = $table['table_number'];
            $status = $table['status'];
        }
    }

    // Fetch all tables for display
    $tables = $pdo->query("SELECT * FROM tables ORDER BY table_number")->fetchAll();

    // Get a list of all table IDs that are associated with at least one order
    $stmt = $pdo->query("SELECT DISTINCT table_id FROM orders WHERE table_id IS NOT NULL");
    $tables_with_orders = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? $error; // Overwrite if error in URL

require_once '../includes/header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h2">Table Management</h1>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#tableModal">Add New Table</button>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Table Number / Name</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tables as $table): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($table['table_number']); ?></td>
                            <td>
                                <span class="badge <?php echo $table['status'] === 'available' ? 'bg-success' : 'bg-danger'; ?>">
                                    <?php echo ucfirst(htmlspecialchars($table['status'])); ?>
                                </span>
                            </td>
                            <td>
                                <a href="tables.php?action=edit&id=<?php echo $table['id']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                <?php 
                                $is_deletable = !in_array($table['id'], $tables_with_orders);
                                if ($is_deletable): 
                                ?>
                                    <form action="tables.php" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this table?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $table['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                    </form>
                                <?php else: ?>
                                    <button type="button" class="btn btn-sm btn-outline-danger" disabled title="Cannot delete: Table has past orders associated with it.">Delete</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="tableModal" tabindex="-1" aria-labelledby="tableModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="tables.php" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="tableModalLabel"><?php echo ($action === 'edit') ? 'Edit' : 'Add'; ?> Table</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="<?php echo ($action === 'edit' && $table_id) ? 'update' : 'create'; ?>">
                    <?php if ($action === 'edit' && $table_id): ?>
                        <input type="hidden" name="id" value="<?php echo $table_id; ?>">
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="table_number" class="form-label">Table Number / Name</label>
                        <input type="text" class="form-control" id="table_number" name="table_number" value="<?php echo htmlspecialchars($table_number); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="available" <?php echo ($status === 'available') ? 'selected' : ''; ?>>Available</option>
                            <option value="occupied" <?php echo ($status === 'occupied') ? 'selected' : ''; ?>>Occupied</option>
                        </select>
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
// If we are in edit mode, we need to show the modal via JS
if ($action === 'edit' && $table_id):
?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var editModal = new bootstrap.Modal(document.getElementById('tableModal'));
        editModal.show();
    });
</script>
<?php
endif;

require_once '../includes/footer.php';
?>
