<?php
include '../_base.php';

auth('Member');

$session_id = req('session_id', '');
if (!$session_id) {
    temp('info', 'Missing Stripe session ID');
    redirect('checkout.php');
}

if (!stripe_secret_key()) {
    temp('info', 'Stripe secret key not configured.');
    redirect('checkout.php');
}

$json = stripe_retrieve_checkout_session($session_id);
if (!$json) {
    temp('info', 'Could not verify payment session.');
    redirect('checkout.php');
}

if (($json->payment_status ?? '') !== 'paid') {
    temp('info', 'Payment not completed.');
    redirect('checkout.php');
}

if (isset($json->metadata->user_id) && (int) $json->metadata->user_id !== (int) $_user->id) {
    temp('info', 'Payment session does not match your account.');
    redirect('checkout.php');
}

$pending_id = $json->metadata->pending_id ?? '';
if (!$pending_id) {
    temp('info', 'Invalid payment session metadata.');
    redirect('checkout.php');
}

$result = finalize_stripe_pending_order($pending_id, 'success');

if (!$result['ok']) {
    if ($result['error'] === 'pending_not_found') {
        $amount_total = ($json->amount_total ?? 0) / 100;
        $order_id = stripe_find_recent_order($_user->id, $amount_total);
        if ($order_id) {
            unset($_SESSION['pending_order']);
            set_cart();
            $_SESSION['review_prompt_order'] = $order_id;
            temp('info', 'Checkout successful');
            redirect("detail.php?id=$order_id");
        }
    }

    temp('info', 'Finalizing order failed: ' . ($result['error'] ?? 'unknown'));
    redirect('cart.php');
}

$order_id = (int) $result['order_id'];

unset($_SESSION['pending_order']);
set_cart();

if (empty($result['already'])) {
    $points_used = (int) ($result['points_used'] ?? 0);
    $earned      = (int) ($result['earned'] ?? 0);
    $_SESSION['user']->points = ($_SESSION['user']->points ?? 0) - $points_used + $earned;
}

$_SESSION['review_prompt_order'] = $order_id;
$_SESSION['stripe_order_by_pending'][$pending_id] = $order_id;

temp('info', 'Checkout successful');
redirect("detail.php?id=$order_id");
