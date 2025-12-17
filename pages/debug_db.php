<?php
require_once '../config/db.php';

try {
    $stmt = $pdo->query("SELECT * FROM company_settings");
    $settings = $stmt->fetchAll();
    print_r($settings);
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage();
}
?>