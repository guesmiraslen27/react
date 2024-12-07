<?php
function verifyAdminPassword($password) {
    // Add your admin password verification logic here
    $admin_password = "your_admin_password"; // Replace with your actual admin password
    return $password === $admin_password;
}

function logBankSettingsChange($adminId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        INSERT INTO admin_logs (
            admin_id,
            action,
            created_at
        ) VALUES (?, 'bank_settings_updated', NOW())
    ");
    
    $stmt->execute([$adminId]);
}
?> 