<?php
require_once 'paypal_config.php';
require_once '../db_config.php';

use PayPalCheckoutSdk\Orders\OrdersCaptureRequest;

if (isset($_GET['token'])) {
    try {
        $request = new OrdersCaptureRequest($_GET['token']);
        $response = $client->execute($request);
        
        if ($response->result->status === 'COMPLETED') {
            // Update bank balance
            $amount = $response->result->purchase_units[0]->amount->value;
            
            $stmt = $pdo->prepare("
                UPDATE bank_settings 
                SET balance = balance + ? 
                WHERE id = 1
            ");
            $stmt->execute([$amount]);
            
            // Record transaction
            $stmt = $pdo->prepare("
                INSERT INTO bank_transactions (
                    transaction_type,
                    amount,
                    source_type,
                    reference_id,
                    status,
                    description
                ) VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                'incoming',
                $amount,
                'paypal',
                $response->result->id,
                'completed',
                'PayPal payment received'
            ]);

            header('Location: /payment_success.html');
        } else {
            header('Location: /payment_failed.html');
        }
    } catch (Exception $e) {
        error_log("PayPal capture error: " . $e->getMessage());
        header('Location: /payment_failed.html');
    }
} 