<?php
session_start();
require_once 'db_config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['count' => 0]);
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM cart WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $count = $stmt->fetch()['count'];
    
    echo json_encode(['count' => $count]);
} catch(PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?> 