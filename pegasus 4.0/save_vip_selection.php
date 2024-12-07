<?php
session_start();
require_once 'db_config.php';

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['tier']) || !isset($data['price']) || !isset($data['vipCode'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid data']);
    exit();
}

try {
    // Verify VIP code exists and is unused
    $stmt = $pdo->prepare("
        SELECT id 
        FROM vip_codes 
        WHERE code = ? 
        AND is_used = FALSE
    ");
    
    $stmt->execute([$data['vipCode']]);
    $vipCode = $stmt->fetch();

    if (!$vipCode) {
        echo json_encode(['success' => false, 'error' => 'Invalid or used VIP code']);
        exit();
    }

    // Store selection in session
    $_SESSION['vip_selection'] = [
        'tier' => $data['tier'],
        'price' => $data['price'],
        'code' => $data['vipCode'],
        'code_id' => $vipCode['id']
    ];

    echo json_encode(['success' => true]);
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?> 