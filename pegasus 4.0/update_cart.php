<?php
session_start();
require_once 'db_config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$itemId = $data['item_id'];
$change = $data['change'];

try {
    $pdo->beginTransaction();
    
    // Get current quantity
    $stmt = $pdo->prepare("SELECT quantity FROM cart WHERE id = ? AND user_id = ?");
    $stmt->execute([$itemId, $_SESSION['user_id']]);
    $currentQty = $stmt->fetch(PDO::FETCH_COLUMN);
    
    $newQty = max(0, $currentQty + $change);
    
    if ($newQty == 0) {
        $stmt = $pdo->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
        $stmt->execute([$itemId, $_SESSION['user_id']]);
    } else {
        $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$newQty, $itemId, $_SESSION['user_id']]);
    }
    
    // Get updated cart count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM cart WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $count = $stmt->fetch(PDO::FETCH_COLUMN);
    
    $pdo->commit();
    
    echo json_encode(['success' => true, 'cartCount' => $count]);
} catch(PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['error' => $e->getMessage()]);
}
?> 