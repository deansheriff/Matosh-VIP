<?php
require_once '../includes/auth.php';
require_once '../config/db.php';

// This page is for Admins only
if (!has_role('Admin')) {
    die('Access Denied: You do not have permission to manage users.');
}

$action = $_POST['action'] ?? $_GET['action'] ?? 'list';
$user_id_to_edit = $_REQUEST['id'] ?? null;

$name = '';
$email = '';
$role = 'Cashier';
$error = '';
$success = '';

try {
    // Handle POST requests for CUD operations
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = $_POST['name'] ?? '';
        $email = $_POST['email'] ?? '';
        $role = $_POST['role'] ?? '';
        $password = $_POST['password'] ?? '';

        if ($action === 'create' || $action === 'update') {
            if (empty($name) || empty($email) || empty($role)) {
                $error = 'Name, email, and role are required.';
            } elseif ($action === 'create' && empty($password)) {
                $error = 'Password is required for new users.';
            } else {
                if ($action === 'create') {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$name, $email, $hashed_password, $role]);
                    $success = 'User created successfully.';
                } elseif ($action === 'update' && $user_id_to_edit) {
                    if (!empty($password)) {
                        // Update with new password
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, role = ?, password = ? WHERE id = ?");
                        $stmt->execute([$name, $email, $role, $hashed_password, $user_id_to_edit]);
                    } else {
                        // Update without changing password
                        $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, role = ? WHERE id = ?");
                        $stmt->execute([$name, $email, $role, $user_id_to_edit]);
                    }
                    $success = 'User updated successfully.';
                }
                header("Location: users.php?success=" . urlencode($success));
                exit;
            }
        } elseif ($action === 'delete' && $user_id_to_edit) {
            if ($user_id_to_edit == $_SESSION['user_id']) {
                $error = 'You cannot delete yourself.';
            } else {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$user_id_to_edit]);
                $success = 'User deleted successfully.';
            }
            $location = "users.php?" . ($error ? "error=" . urlencode($error) : "success=" . urlencode($success));
            header("Location: $location");
            exit;
        }
    }

    // Handle GET request for editing a user
    if ($action === 'edit' && $user_id_to_edit) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id_to_edit]);
        $user = $stmt->fetch();
        if ($user) {
            $name = $user['name'];
            $email = $user['email'];
            $role = $user['role'];
        }
    }

    // Fetch all users for display
    $users = $pdo->query("SELECT id, name, email, role FROM users ORDER BY name")->fetchAll();

} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
    if (str_contains($e->getMessage(), 'Duplicate entry')) {
        $error = 'A user with this email address already exists.';
    }
}

$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? $error;

require_once '../includes/header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h2">User Management</h1>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal">Add New User</button>
    </div>

    <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

    <div class="card">
        <div class="card-body">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['name']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><span class="badge bg-secondary"><?php echo htmlspecialchars($user['role']); ?></span></td>
                            <td>
                                <a href="users.php?action=edit&id=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                <form action="users.php" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
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
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="userModal" tabindex="-1" aria-labelledby="userModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="users.php" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="userModalLabel"><?php echo ($action === 'edit') ? 'Edit' : 'Add'; ?> User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="<?php echo ($action === 'edit' && $user_id_to_edit) ? 'update' : 'create'; ?>">
                    <?php if ($action === 'edit' && $user_id_to_edit): ?>
                        <input type="hidden" name="id" value="<?php echo $user_id_to_edit; ?>">
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="name" class="form-label">Full Name</label>
                        <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($name); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="role" class="form-label">Role</label>
                        <select name="role" id="role" class="form-select" required>
                            <option value="Cashier" <?php echo ($role === 'Cashier') ? 'selected' : ''; ?>>Cashier</option>
                            <option value="Waiter" <?php echo ($role === 'Waiter') ? 'selected' : ''; ?>>Waiter</option>
                            <option value="Kitchen" <?php echo ($role === 'Kitchen') ? 'selected' : ''; ?>>Kitchen</option>
                            <option value="Admin" <?php echo ($role === 'Admin') ? 'selected' : ''; ?>>Admin</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" <?php echo ($action !== 'edit') ? 'required' : ''; ?>>
                        <?php if ($action === 'edit'): ?>
                            <div class="form-text">Leave blank to keep the current password.</div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
if ($action === 'edit' && $user_id_to_edit):
?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var editModal = new bootstrap.Modal(document.getElementById('userModal'));
        editModal.show();
    });
</script>
<?php
endif;

require_once '../includes/footer.php';
?>
