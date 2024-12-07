<?php
session_start();
require_once 'db_config.php';

$data = json_decode(file_get_contents('php://input'), true);
$amount = floatval($data['amount'] ?? 0);

const MINIMUM_AMOUNT = 325.00;

try {
    if ($amount < MINIMUM_AMOUNT) {
        echo json_encode([
            'success' => false,
            'error' => "Minimum VIP membership amount is $" . MINIMUM_AMOUNT
        ]);
        exit();
    }

    echo json_encode([
        'success' => true,
        'amount' => $amount
    ]);

} catch(Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Validation error']);
}
?> 