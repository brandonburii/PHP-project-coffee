<?php
include '../_base.php';

// Stripe webhook receiver. Configure endpoint: /order/stripe_webhook.php

$raw = file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
$webhook_secret = stripe_webhook_secret();

if (!$webhook_secret) {
    http_response_code(400);
    echo 'Missing STRIPE_WEBHOOK_SECRET';
    exit;
}

// Verify signature (Stripe v1)
$parts = explode(',', $sig_header);
$t = null;
$v1 = [];
foreach ($parts as $p) {
    [$k, $v] = explode('=', $p, 2) + [null, null];
    if ($k === 't') {
        $t = $v;
    }
    if ($k === 'v1') {
        $v1[] = $v;
    }
}

if (!$t || empty($v1)) {
    http_response_code(400);
    echo 'Invalid signature header';
    exit;
}

$signed_payload = $t . '.' . $raw;
$expected_sig   = hash_hmac('sha256', $signed_payload, $webhook_secret);
$valid          = false;

foreach ($v1 as $s) {
    if (hash_equals($expected_sig, $s)) {
        $valid = true;
        break;
    }
}

if (!$valid) {
    http_response_code(400);
    echo 'Signature verification failed';
    exit;
}

$event = json_decode($raw);

if (isset($event->type) && $event->type === 'checkout.session.completed') {
    $session = $event->data->object ?? null;

    if (!$session) {
        http_response_code(200);
        echo 'ignored';
        exit;
    }

    if (($session->payment_status ?? '') !== 'paid') {
        http_response_code(200);
        echo 'ignored unpaid';
        exit;
    }

    $pending_id = $session->metadata->pending_id ?? null;

    if ($pending_id) {
        $result = finalize_stripe_pending_order($pending_id, 'webhook');

        if ($result['ok']) {
            http_response_code(200);
            echo 'ok';
            exit;
        }

        if ($result['error'] === 'pending_not_found') {
            http_response_code(200);
            echo 'already processed';
            exit;
        }

        http_response_code(500);
        echo 'finalize failed';
        exit;
    }
}

http_response_code(200);
echo 'ignored';
