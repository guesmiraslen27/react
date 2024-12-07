<?php
require_once 'db_config.php';

function processPayment($amount, $sourceType, $referenceId, $description) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();

        // Get current bank balance
        $stmt = $pdo->prepare("SELECT balance FROM bank_settings WHERE id = 1 FOR UPDATE");
        $stmt->execute();
        $currentBalance = $stmt->fetch(PDO::FETCH_COLUMN);

        // Record the transaction
        $stmt = $pdo->prepare("
            INSERT INTO bank_transactions (
                transaction_type,
                amount,
                source_type,
                reference_id,
                description,
                status
            ) VALUES (?, ?, ?, ?, ?, 'completed')
        ");

        // Determine if this is incoming or outgoing
        $transactionType = ($sourceType === 'vip_bonus') ? 'outgoing' : 'incoming';
        
        $stmt->execute([
            $transactionType,
            $amount,
            $sourceType,
            $referenceId,
            $description
        ]);

        // Update bank balance
        $newBalance = ($transactionType === 'incoming') 
            ? $currentBalance + $amount 
            : $currentBalance - $amount;

        $stmt = $pdo->prepare("
            UPDATE bank_settings 
            SET balance = ? 
            WHERE id = 1
        ");
        $stmt->execute([$newBalance]);

        $pdo->commit();
        return true;

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Payment processing error: " . $e->getMessage());
        return false;
    }
} 