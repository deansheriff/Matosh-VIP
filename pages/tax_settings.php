<?php
require_once '../includes/auth.php';
require_once '../config/db.php';

// Check for Admin role
if (!has_role('Admin')) {
    die('Access Denied: You do not have permission to manage tax settings.');
}

$action = $_POST['action'] ?? $_GET['action'] ?? 'list';
$tax_id = $_REQUEST['id'] ?? null;

$tax_name = '';
$tax_rate = '';
$tax_type = 'percentage';
$is_active = 0;
$error = '';
$success = '';

try {
    // Handle POST requests for CUD operations
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $tax_name = $_POST['tax_name'] ?? '';
        $tax_rate = $_POST['tax_rate'] ?? '';
        $tax_type = $_POST['tax_type'] ?? 'percentage';
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if ($action === 'create' || $action === 'update') {
            if (empty($tax_name) || empty($tax_rate)) {
                $error = 'All fields are required.';
            } else {
                // If setting a tax as active, deactivate all others
                if ($is_active) {
                    $pdo->exec("UPDATE tax_settings SET is_active = 0");
                }

                if ($action === 'create') {
                    $stmt = $pdo->prepare("INSERT INTO tax_settings (tax_name, tax_rate, tax_type, is_active) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$tax_name, $tax_rate, $tax_type, $is_active]);
                    $success = 'Tax setting created successfully.';
                } elseif ($action === 'update' && $tax_id) {
                    $stmt = $pdo->prepare("UPDATE tax_settings SET tax_name = ?, tax_rate = ?, tax_type = ?, is_active = ? WHERE id = ?");
                    $stmt->execute([$tax_name, $tax_rate, $tax_type, $is_active, $tax_id]);
                    $success = 'Tax setting updated successfully.';
                }
                // Redirect to avoid form resubmission
                header("Location: tax_settings.php?success=" . urlencode($success));
                exit;
            }
        } elseif ($action === 'delete' && $tax_id) {
            $stmt = $pdo->prepare("DELETE FROM tax_settings WHERE id = ?");
            $stmt->execute([$tax_id]);
            $success = 'Tax setting deleted successfully.';
            header("Location: tax_settings.php?success=" . urlencode($success));
            exit;
        }
    }

    // Handle GET request for editing a tax
    if ($action === 'edit' && $tax_id) {
        $stmt = $pdo->prepare("SELECT * FROM tax_settings WHERE id = ?");
        $stmt->execute([$tax_id]);
        $tax = $stmt->fetch();
        if ($tax) {
            $tax_name = $tax['tax_name'];
            $tax_rate = $tax['tax_rate'];
            $tax_type = $tax['tax_type'];
            $is_active = $tax['is_active'];
        }
    }

    // Fetch all tax settings for display
    $tax_settings = $pdo->query("SELECT * FROM tax_settings ORDER BY tax_name")->fetchAll();

} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

$success = $_GET['success'] ?? '';

require_once '../includes/header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h2">Tax Settings</h1>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#taxModal">Add New Tax</button>
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
                        <th>Tax Name</th>
                        <th>Rate</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tax_settings as $tax): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($tax['tax_name']); ?></td>
                            <td><?php echo htmlspecialchars($tax['tax_rate']); ?><?php echo $tax['tax_type'] === 'percentage' ? '%' : ''; ?></td>
                            <td><?php echo ucfirst(htmlspecialchars($tax['tax_type'])); ?></td>
                            <td>
                                <span class="badge <?php echo $tax['is_active'] ? 'bg-success' : 'bg-secondary'; ?>">
                                    <?php echo $tax['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td>
                                <a href="tax_settings.php?action=edit&id=<?php echo $tax['id']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                <form action="tax_settings.php" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this tax?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $tax['id']; ?>">
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
<div class="modal fade" id="taxModal" tabindex="-1" aria-labelledby="taxModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="tax_settings.php" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="taxModalLabel"><?php echo ($action === 'edit') ? 'Edit' : 'Add'; ?> Tax</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="<?php echo ($action === 'edit' && $tax_id) ? 'update' : 'create'; ?>">
                    <?php if ($action === 'edit' && $tax_id): ?>
                        <input type="hidden" name="id" value="<?php echo $tax_id; ?>">
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="tax_name" class="form-label">Tax Name</label>
                        <input type="text" class="form-control" id="tax_name" name="tax_name" value="<?php echo htmlspecialchars($tax_name); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="tax_rate" class="form-label">Tax Rate</label>
                        <input type="number" step="0.01" class="form-control" id="tax_rate" name="tax_rate" value="<?php echo htmlspecialchars($tax_rate); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="tax_type" class="form-label">Tax Type</label>
                        <select class="form-select" id="tax_type" name="tax_type">
                            <option value="percentage" <?php echo $tax_type === 'percentage' ? 'selected' : ''; ?>>Percentage</option>
                            <option value="fixed" <?php echo $tax_type === 'fixed' ? 'selected' : ''; ?>>Fixed Amount</option>
                        </select>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?php echo $is_active ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="is_active">
                            Set as Active
                        </label>
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
if ($action === 'edit' && $tax_id):
?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var editModal = new bootstrap.Modal(document.getElementById('taxModal'));
        editModal.show();
    });
</script>
<?php
endif;

require_once '../includes/footer.php';
?>
