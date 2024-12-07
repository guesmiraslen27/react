<?php
session_start();
require_once 'db_config.php';

$data = json_decode(file_get_contents('php://input'), true);
$code = $data['code'] ?? '';

try {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'Please login first']);
        exit();
    }

    // Verify the promotional code
    $stmt = $pdo->prepare("
        SELECT 
            vc.id,
            vc.code_type,
            vc.owner_member_id,
            vm.user_id as owner_user_id,
            vm.plan_type
        FROM vip_codes vc
        JOIN vip_memberships vm ON vc.owner_member_id = vm.id
        WHERE vc.code = ? AND vc.code_type = 'promotion'
    ");
    $stmt->execute([$code]);
    $promoCode = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$promoCode) {
        echo json_encode(['success' => false, 'error' => 'Invalid promotional code']);
        exit();
    }

    // Verify the code belongs to the user
    if ($promoCode['owner_user_id'] !== $_SESSION['user_id']) {
        echo json_encode(['success' => false, 'error' => 'This promotional code belongs to another user']);
        exit();
    }

    // Calculate discount based on VIP tier
    $discount = 0;
    switch(strtolower($promoCode['plan_type'])) {
        case 'bronze':
            $discount = 10;
            break;
        case 'silver':
            $discount = 20;
            break;
        case 'gold':
            $discount = 30;
            break;
    }

    echo json_encode([
        'success' => true,
        'discount' => $discount,
        'message' => "Discount of {$discount}% applied!"
    ]);

} catch(PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?> 