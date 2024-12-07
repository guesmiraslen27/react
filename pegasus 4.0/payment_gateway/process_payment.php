<?php
require_once 'paypal_config.php';
require_once '../db_config.php';

use PayPalCheckoutSdk\Orders\OrdersCreateRequest;
use PayPalCheckoutSdk\Orders\OrdersCaptureRequest;

function createPayPalOrder($amount, $currency = 'USD') {
    global $client;
    
    $request = new OrdersCreateRequest();
    $request->prefer('return=representation');
    
    $request->body = [
        "intent" => "CAPTURE",
        "purchase_units" => [[
            "amount" => [
                "currency_code" => $currency,
                "value" => number_format($amount, 2, '.', '')
            ]
        ]],
        "application_context" => [
            "return_url" => "https://yourdomain.com/payment_gateway/capture_payment.php",
            "cancel_url" => "https://yourdomain.com/payment_failed.html"
        ]
    ];

    try {
        $response = $client->execute($request);
        return [
            'success' => true,
            'order_id' => $response->result->id,
            'approval_url' => $response->result->links[1]->href
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
} 