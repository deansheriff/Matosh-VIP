<?php
require_once '../includes/auth.php';
require_once '../config/db.php';

// Check for Admin/Cashier role
if (!has_any_role(['Admin', 'Cashier'])) {
    die('Access Denied: You do not have permission to manage the menu.');
}

$action = $_POST['action'] ?? $_GET['action'] ?? 'list';
$item_id = $_REQUEST['id'] ?? null;

$name = '';
$category = '';
$price = '';
$availability = 1;
$image_path = null;
$error = '';
$success = '';

try {
    // Handle POST requests for CUD operations
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = $_POST['name'] ?? '';
        $category = $_POST['category'] ?? '';
        $price = $_POST['price'] ?? '';
        $availability = isset($_POST['availability']) ? 1 : 0;
        $image_path = $_POST['existing_image'] ?? null;

        if ($action === 'create' || $action === 'update') {
            if (empty($name) || empty($category) || empty($price)) {
                $error = 'All fields are required.';
            } else {
                // Handle image upload
                if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = '../assets/images/products/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                    $max_size = 5 * 1024 * 1024; // 5MB

                    if (in_array($_FILES['image']['type'], $allowed_types) && $_FILES['image']['size'] <= $max_size) {
                        // Delete old image if updating
                        if ($action === 'update' && $image_path) {
                            if (file_exists($upload_dir . $image_path)) {
                                unlink($upload_dir . $image_path);
                            }
                        }
                        $file_ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                        $new_filename = uniqid('prod_', true) . '.' . $file_ext;
                        if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $new_filename)) {
                            $image_path = $new_filename;
                        } else {
                            $error = 'Failed to move uploaded file.';
                        }
                    } else {
                        $error = 'Invalid file type or size (Max 5MB, JPG/PNG/GIF).';
                    }
                }

                if (!$error) {
                    if ($action === 'create') {
                        $stmt = $pdo->prepare("INSERT INTO menu_items (name, category, price, availability, image) VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([$name, $category, $price, $availability, $image_path]);
                        $success = 'Menu item created successfully.';
                    } elseif ($action === 'update' && $item_id) {
                        $stmt = $pdo->prepare("UPDATE menu_items SET name = ?, category = ?, price = ?, availability = ?, image = ? WHERE id = ?");
                        $stmt->execute([$name, $category, $price, $availability, $image_path, $item_id]);
                        $success = 'Menu item updated successfully.';
                    }
                    header("Location: menu.php?success=" . urlencode($success));
                    exit;
                }
            }
        } elseif ($action === 'delete' && $item_id) {
            // First, get the image path to delete the file
            $stmt = $pdo->prepare("SELECT image FROM menu_items WHERE id = ?");
            $stmt->execute([$item_id]);
            $image_to_delete = $stmt->fetchColumn();

            if ($image_to_delete) {
                $file_path = '../assets/images/products/' . $image_to_delete;
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
            }

            $stmt = $pdo->prepare("DELETE FROM menu_items WHERE id = ?");
            $stmt->execute([$item_id]);
            $success = 'Menu item deleted successfully.';
            header("Location: menu.php?success=" . urlencode($success));
            exit;
        }
    }

    // Handle GET request for editing an item
    if ($action === 'edit' && $item_id) {
        $stmt = $pdo->prepare("SELECT * FROM menu_items WHERE id = ?");
        $stmt->execute([$item_id]);
        $item = $stmt->fetch();
        if ($item) {
            $name = $item['name'];
            $category = $item['category'];
            $price = $item['price'];
            $availability = $item['availability'];
            $image_path = $item['image'];
        }
    }

    // Fetch all menu items for display
    $menu_items = $pdo->query("SELECT * FROM menu_items ORDER BY category, name")->fetchAll();

} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

$success = $_GET['success'] ?? '';

require_once '../includes/header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h2">Menu Management</h1>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#menuItemModal">Add New Item</button>
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
                        <th>Image</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Availability</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($menu_items as $item): ?>
                        <tr>
                            <td>
                                <?php if ($item['image']): ?>
                                    <img src="../assets/images/products/<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" width="50" height="50" class="rounded">
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($item['name']); ?></td>
                            <td><?php echo htmlspecialchars($item['category']); ?></td>
                            <td><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($item['price'], 2); ?></td>
                            <td>
                                <span class="badge <?php echo $item['availability'] ? 'bg-success' : 'bg-secondary'; ?>">
                                    <?php echo $item['availability'] ? 'Available' : 'Unavailable'; ?>
                                </span>
                            </td>
                            <td>
                                <a href="menu.php?action=edit&id=<?php echo $item['id']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                <form action="menu.php" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this item?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
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
<div class="modal fade" id="menuItemModal" tabindex="-1" aria-labelledby="menuItemModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="menu.php" method="POST" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title" id="menuItemModalLabel"><?php echo ($action === 'edit') ? 'Edit' : 'Add'; ?> Menu Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="<?php echo ($action === 'edit' && $item_id) ? 'update' : 'create'; ?>">
                    <?php if ($action === 'edit' && $item_id): ?>
                        <input type="hidden" name="id" value="<?php echo $item_id; ?>">
                        <input type="hidden" name="existing_image" value="<?php echo htmlspecialchars($image_path); ?>">
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="name" class="form-label">Item Name</label>
                        <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($name); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="category" class="form-label">Category</label>
                        <input type="text" class="form-control" id="category" name="category" value="<?php echo htmlspecialchars($category); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="price" class="form-label">Price</label>
                        <input type="number" step="0.01" class="form-control" id="price" name="price" value="<?php echo htmlspecialchars($price); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="image" class="form-label">Item Image</label>
                        <input type="file" class="form-control" id="image" name="image" accept="image/*">
                        <?php if ($action === 'edit' && $image_path): ?>
                            <div class="mt-2">
                                <img src="../assets/images/products/<?php echo htmlspecialchars($image_path); ?>" alt="Current Image" width="100">
                                <p class="form-text">Current image. Upload a new one to replace it.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="availability" name="availability" value="1" <?php echo $availability ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="availability">
                            Available
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
if ($action === 'edit' && $item_id):
?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var editModal = new bootstrap.Modal(document.getElementById('menuItemModal'));
        document.getElementById('menuItemModalLabel').textContent = 'Edit Menu Item';
        editModal.show();
    });
</script>
<?php
endif;
?>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const menuItemModal = new bootstrap.Modal(document.getElementById('menuItemModal'));
        const modalLabel = document.getElementById('menuItemModalLabel');
        const form = document.getElementById('menuItemModal').querySelector('form');
        const actionInput = form.querySelector('input[name="action"]');
        const idInput = form.querySelector('input[name="id"]');
        const existingImageDiv = form.querySelector('.mt-2');

        // Reset form for creating a new item
        document.querySelector('button[data-bs-target="#menuItemModal"]').addEventListener('click', function () {
            form.reset();
            actionInput.value = 'create';
            if(idInput) idInput.value = '';
            modalLabel.textContent = 'Add Menu Item';
            if (existingImageDiv) {
                existingImageDiv.remove();
            }
        });
    });
</script>
<?php
require_once '../includes/footer.php';
?>
