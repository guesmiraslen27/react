<?php
require_once 'paypal_config.php';
require_once '../db_config.php';

use PayPalPayouts\PayoutsPostRequest;

function sendBonus($email, $amount, $description) {
    global $client;
    
    $request = new PayoutsPostRequest();
    $request->body = [
        "sender_batch_header" => [
            "sender_batch_id" => uniqid(),
            "email_subject" => "You received a bonus payment!",
            "email_message" => $description
        ],
        "items" => [[
            "recipient_type" => "EMAIL",
            "amount" => [
                "value" => number_format($amount, 2, '.', ''),
                "currency" => "USD"
            ],
            "receiver" => $email,
            "note" => $description
        ]]
    ];

    try {
        $response = $client->execute($request);
        return [
            'success' => true,
            'payout_id' => $response->result->batch_header->payout_batch_id
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
} 