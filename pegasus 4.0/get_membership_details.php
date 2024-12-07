<?php
session_start();
require_once 'db_config.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['order'])) {
    echo json_encode(['success' => false]);
    exit();
}

try {
    $stmt = $pdo->prepare("
        SELECT plan_type, monthly_fee, start_date 
        FROM vip_memberships 
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$_GET['order'], $_SESSION['user_id']]);
    $membership = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($membership) {
        echo json_encode([
            'success' => true,
            'plan' => $membership['plan_type'],
            'fee' => $membership['monthly_fee'],
            'startDate' => $membership['start_date']
        ]);
    } else {
        echo json_encode(['success' => false]);
    }
} catch(PDOException $e) {
    echo json_encode(['success' => false]);
}
?> 