<?php
session_start();
require_once 'db_config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Please login first']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = $_POST['product_id'];
    $size = $_POST['size'];
    $user_id = $_SESSION['user_id'];

    try {
        $stmt = $pdo->prepare("INSERT INTO cart (user_id, product_id, size) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $product_id, $size]);
        
        // Get cart count
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM cart WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $count = $stmt->fetch()['count'];
        
        echo json_encode(['success' => true, 'cartCount' => $count]);
    } catch(PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}
?> 