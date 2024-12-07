<?php
session_start();
require_once 'db_config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Please login first']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$upgradeAmount = floatval($data['amount'] ?? 0);

try {
    // Get current membership details
    $stmt = $pdo->prepare("
        SELECT id, membership_amount 
        FROM vip_memberships 
        WHERE user_id = ? AND status = 'active'
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $membership = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$membership) {
        echo json_encode(['success' => false, 'error' => 'No active VIP membership found']);
        exit();
    }

    if ($upgradeAmount <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid upgrade amount']);
        exit();
    }

    $pdo->beginTransaction();

    // Record the upgrade
    $newTotal = $membership['membership_amount'] + $upgradeAmount;
    
    $stmt = $pdo->prepare("
        INSERT INTO vip_membership_upgrades (
            membership_id,
            previous_amount,
            upgrade_amount,
            total_amount
        ) VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([
        $membership['id'],
        $membership['membership_amount'],
        $upgradeAmount,
        $newTotal
    ]);

    // Update the membership amount
    $stmt = $pdo->prepare("
        UPDATE vip_memberships 
        SET membership_amount = ? 
        WHERE id = ?
    ");
    $stmt->execute([$newTotal, $membership['id']]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'newTotal' => $newTotal,
        'message' => 'Membership successfully upgraded!'
    ]);

} catch(PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Database error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?> 