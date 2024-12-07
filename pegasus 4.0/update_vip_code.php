<?php
require_once 'db_config.php';
session_start();

if (!isset($_SESSION['user_id']) || !isset($_POST['vip_code'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit();
}

try {
    $stmt = $pdo->prepare("
        UPDATE vip_codes 
        SET is_used = TRUE, 
            used_by = ? 
        WHERE code = ? 
        AND is_used = FALSE
    ");
    
    $stmt->execute([$_SESSION['user_id'], $_POST['vip_code']]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Code already used or invalid']);
    }

} catch(PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?> 