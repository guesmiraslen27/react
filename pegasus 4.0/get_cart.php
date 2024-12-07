<?php
session_start();
require_once 'db_config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit();
}

try {
    $stmt = $pdo->prepare("
        SELECT c.id, p.name, p.price, p.image_url, c.size, c.quantity
        FROM cart c
        JOIN products p ON c.product_id = p.id
        WHERE c.user_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($items);
} catch(PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?> 