<?php
require_once '../includes/auth.php';
require_once '../config/db.php';

// This page is for Admins only
if (!has_role('Admin')) {
    die('Access Denied: You do not have permission to manage company settings.');
}

$error = '';
$success = '';

try {
    // Fetch current company settings
    $stmt = $pdo->query("SELECT * FROM company_settings WHERE id = 1");
    $settings = $stmt->fetch();

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = $_POST['name'] ?? '';
        $address = $_POST['address'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $email = $_POST['email'] ?? '';
        $currency_symbol = $_POST['currency_symbol'] ?? '';
        $logo_path = $settings['logo'];

        // Handle logo upload
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../assets/images/';
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $max_size = 5 * 1024 * 1024; // 5MB

            $file_info = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($file_info, $_FILES['logo']['tmp_name']);
            finfo_close($file_info);

            if (in_array($mime_type, $allowed_types) && $_FILES['logo']['size'] <= $max_size) {
                // Generate a unique filename
                $file_ext = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
                $new_filename = 'logo_' . uniqid() . '.' . $file_ext;

                if (move_uploaded_file($_FILES['logo']['tmp_name'], $upload_dir . $new_filename)) {
                    // Delete old logo if it exists and the new one is uploaded
                    if ($settings['logo'] && file_exists($upload_dir . $settings['logo'])) {
                        unlink($upload_dir . $settings['logo']);
                    }
                    $logo_path = $new_filename;
                } else {
                    $error = 'Failed to move uploaded file.';
                }
            } else {
                $error = 'Invalid file type or size (Max 5MB, JPG/PNG/GIF).';
            }
        }

        if (!$error) {
            $stmt = $pdo->prepare("UPDATE company_settings SET name = ?, address = ?, phone = ?, email = ?, currency_symbol = ?, logo = ? WHERE id = 1");
            $stmt->execute([$name, $address, $phone, $email, $currency_symbol, $logo_path]);
            $success = 'Company settings updated successfully.';
            // Refresh settings
            $stmt = $pdo->query("SELECT * FROM company_settings WHERE id = 1");
            $settings = $stmt->fetch();
        }
    }
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

require_once '../includes/header.php';
?>

<div class="container">
    <h1 class="h2 mb-4">Company Settings</h1>

    <div class="card">
        <div class="card-body">
            <form action="company_settings.php" method="POST" enctype="multipart/form-data">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="name" class="form-label">Company Name</label>
                            <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($settings['name'] ?? ''); ?>">
                        </div>
                        <div class="mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($settings['address'] ?? ''); ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone</label>
                            <input type="text" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($settings['phone'] ?? ''); ?>">
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($settings['email'] ?? ''); ?>">
                        </div>
                        <div class="mb-3">
                            <label for="currency_symbol" class="form-label">Currency Symbol</label>
                            <input type="text" class="form-control" id="currency_symbol" name="currency_symbol" value="<?php echo htmlspecialchars($settings['currency_symbol'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="logo" class="form-label">Company Logo</label>
                            <input class="form-control" type="file" id="logo" name="logo">
                        </div>
                        <?php if (!empty($settings['logo'])): ?>
                        <div class="mb-3">
                            <img src="/assets/images/<?php echo htmlspecialchars($settings['logo']); ?>" alt="Company Logo" class="img-thumbnail" width="200">
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Save Settings</button>
            </form>
        </div>
    </div>
</div>

<?php
require_once '../includes/footer.php';
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($error): ?>
    showToast('<?php echo $error; ?>', 'error');
    <?php endif; ?>

    <?php if ($success): ?>
    showToast('<?php echo $success; ?>', 'success');
    <?php endif; ?>
});
</script>
