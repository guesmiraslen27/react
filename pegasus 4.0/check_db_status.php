<?php
require_once 'db_config.php';

function checkDatabaseStatus() {
    global $pdo;
    
    try {
        // Check connection
        $pdo->query("SELECT 1");
        
        // Check required tables
        $requiredTables = [
            'users',
            'products',
            'cart',
            'vip_codes',
            'vip_memberships',
            'orders',
            'bank_settings',
            'bank_transactions'
        ];
        
        $stmt = $pdo->query("SHOW TABLES");
        $existingTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $missingTables = array_diff($requiredTables, $existingTables);
        
        if (!empty($missingTables)) {
            throw new Exception("Missing tables: " . implode(', ', $missingTables));
        }
        
        return [
            'status' => 'ok',
            'message' => 'Database is properly configured'
        ];
        
    } catch (Exception $e) {
        return [
            'status' => 'error',
            'message' => $e->getMessage()
        ];
    }
}

// Use this in your pages
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $status = checkDatabaseStatus();
    echo json_encode($status);
}
?> 