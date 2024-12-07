<?php
session_start();
require_once 'db_config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

try {
    // Get VIP membership ID
    $stmt = $pdo->prepare("
        SELECT id FROM vip_memberships 
        WHERE user_id = ? AND status = 'active'
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $membership = $stmt->fetch();

    if (!$membership) {
        echo json_encode(['success' => false, 'error' => 'Not a VIP member']);
        exit();
    }

    // Get invitation statistics for each code
    $stmt = $pdo->prepare("
        SELECT 
            vc.code,
            vc.code_type,
            vc.successful_invites,
            vc.created_at,
            COUNT(vi.id) as total_invites
        FROM vip_codes vc
        LEFT JOIN vip_invitations vi ON vc.id = vi.code_id
        WHERE vc.owner_member_id = ?
        GROUP BY vc.id
        ORDER BY vc.code_type DESC
    ");
    $stmt->execute([$membership['id']]);
    $codeStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'stats' => $codeStats
    ]);

} catch(PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?> 