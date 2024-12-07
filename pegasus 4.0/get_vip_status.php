<?php
session_start();
require_once 'db_config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

try {
    $stmt = $pdo->prepare("
        SELECT membership_amount, status 
        FROM vip_memberships 
        WHERE user_id = ? AND status = 'active'
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $membership = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($membership) {
        echo json_encode([
            'success' => true,
            'membership_amount' => floatval($membership['membership_amount']),
            'status' => $membership['status']
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'No active membership found']);
    }
} catch(PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?> 