<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$current_page = basename($_SERVER['PHP_SELF']);

// Fetch company settings
$company_stmt = $pdo->query("SELECT * FROM company_settings WHERE id = 1");
$company_settings = $company_stmt->fetch();
if (!defined('CURRENCY_SYMBOL')) {
    define('CURRENCY_SYMBOL', $company_settings['currency_symbol'] ?? '$');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($company_settings['name'] ?? 'Matosh POS'); ?></title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts - Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>

<?php if (isset($_SESSION['user_id'])): ?>
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
    <div class="container-fluid">
        <a class="navbar-brand text-primary fw-bold" href="/index.php">
            <?php if (!empty($company_settings['logo'])): ?>
                <img src="../assets/images/<?php echo htmlspecialchars($company_settings['logo']); ?>" alt="<?php echo htmlspecialchars($company_settings['name']); ?>" height="30" class="d-inline-block align-text-top">
            <?php else: ?>
                <?php echo htmlspecialchars($company_settings['name'] ?? 'Matosh POS'); ?>
            <?php endif; ?>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'index.php') ? 'active' : ''; ?>" href="/index.php">Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'orders.php') ? 'active' : ''; ?>" href="/pages/orders.php">Orders</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'tables.php') ? 'active' : ''; ?>" href="/pages/tables.php">Tables</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'customers.php') ? 'active' : ''; ?>" href="/pages/customers.php">Customers</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'menu.php') ? 'active' : ''; ?>" href="/pages/menu.php">Menu</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>" href="/pages/reports.php">Reports</a>
                </li>
                <?php if (has_role('Admin')): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'users.php') ? 'active' : ''; ?>" href="/pages/users.php">User Management</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'tax_settings.php') ? 'active' : ''; ?>" href="/pages/tax_settings.php">Tax Settings</a>
                </li>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                        <li><a class="dropdown-item" href="#">Profile</a></li>
                        <?php if (has_role('Admin')): ?>
                        <li><a class="dropdown-item" href="/pages/company_settings.php">Company Settings</a></li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="/pages/logout.php">Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>
<?php endif; ?>

<main class="container-fluid mt-4">
