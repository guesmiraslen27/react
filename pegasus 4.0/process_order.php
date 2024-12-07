<?php
session_start();
require_once 'db_config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

try {
    $pdo->beginTransaction();

    // Calculate total from cart
    $stmt = $pdo->prepare("
        SELECT SUM(p.price * c.quantity) as total
        FROM cart c
        JOIN products p ON c.product_id = p.id
        WHERE c.user_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $total = $stmt->fetch(PDO::FETCH_COLUMN);

    // Create order
    $stmt = $pdo->prepare("
        INSERT INTO orders (
            user_id,
            total_amount,
            shipping_address,
            status
        ) VALUES (?, ?, ?, 'pending')
    ");
    
    $address = sprintf("%s, %s, %s, %s",
        $data['shipping']['address'],
        $data['shipping']['city'],
        $data['shipping']['zipCode'],
        $data['shipping']['country']
    );
    
    $stmt->execute([
        $_SESSION['user_id'],
        $total,
        $address
    ]);

    $orderId = $pdo->lastInsertId();

    // Process bank transaction
    $success = processPayment(
        $total,
        'cart',
        $orderId,
        "Order payment for order #" . $orderId
    );

    if (!$success) {
        throw new Exception('Payment processing failed');
    }

    // Move items from cart to order_items
    $stmt = $pdo->prepare("
        INSERT INTO order_items (order_id, product_id, quantity, size, price)
        SELECT ?, c.product_id, c.quantity, c.size, p.price
        FROM cart c
        JOIN products p ON c.product_id = p.id
        WHERE c.user_id = ?
    ");
    $stmt->execute([$orderId, $_SESSION['user_id']]);

    // Clear cart
    $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'orderId' => $orderId
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Order processing error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Order processing failed']);
}
?> 