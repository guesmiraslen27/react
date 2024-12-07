<?php
session_start();
require_once 'db_config.php';

$data = json_decode(file_get_contents('php://input'), true);
$code = $data['code'] ?? '';

try {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'Please login first']);
        exit();
    }

    $pdo->beginTransaction();

    // Get code details including owner info
    $stmt = $pdo->prepare("
        SELECT 
            vc.id as code_id,
            vc.code_type,
            vc.owner_member_id,
            vc.successful_invites,
            vm.user_id as owner_user_id
        FROM vip_codes vc
        LEFT JOIN vip_memberships vm ON vc.owner_member_id = vm.id
        WHERE vc.code = ?
    ");
    $stmt->execute([$code]);
    $codeDetails = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$codeDetails) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => 'Invalid VIP code']);
        exit();
    }

    // If it's an invitation code, track the invitation
    if ($codeDetails['code_type'] === 'invitation') {
        // Store the code in session for bonus processing during payment
        $_SESSION['used_invitation_code'] = $code;
        
        // Update successful invites count
        $stmt = $pdo->prepare("
            UPDATE vip_codes 
            SET successful_invites = successful_invites + 1,
                last_used_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$codeDetails['code_id']]);

        // Record the invitation
        $stmt = $pdo->prepare("
            INSERT INTO vip_invitations (
                code_id,
                inviter_id,
                invited_user_id
            ) VALUES (?, ?, ?)
        ");
        $stmt->execute([
            $codeDetails['code_id'],
            $codeDetails['owner_member_id'],
            $_SESSION['user_id']
        ]);
    }

    // Record usage in history
    $stmt = $pdo->prepare("
        INSERT INTO vip_code_usage (code_id, user_id)
        VALUES (?, ?)
    ");
    $stmt->execute([$codeDetails['code_id'], $_SESSION['user_id']]);

    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'inviteCount' => $codeDetails['successful_invites'] + 1
    ]);

} catch(PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Database error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?> 