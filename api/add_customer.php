<?php
require_once '../config/db.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

$name = $data['name'] ?? '';
$phone = $data['phone'] ?? '';
$email = $data['email'] ?? '';

if (empty($name)) {
    echo json_encode(['success' => false, 'error' => 'Customer name is required.']);
    exit;
}

try {
    $stmt = $pdo->prepare("INSERT INTO customers (name, phone, email) VALUES (?, ?, ?)");
    $stmt->execute([$name, $phone, $email]);
    $new_customer_id = $pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'customer' => [
            'id' => $new_customer_id,
            'name' => $name
        ]
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
