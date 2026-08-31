<?php
include '../_base.php';

// Stripe Checkout session creator (no composer required)
auth('Member');

if (!is_post()) {
    redirect('checkout.php');
}

$code   = req('code', '');
$points = req('points', 0);

// (1) Basic validation: voucher
$voucher = null;
if ($code != '') {
    $check = validate_voucher($code, 0);
    if (!$check['ok']) {
        temp('info', 'Invalid voucher');
        redirect('checkout.php');
    }
    $voucher = $check['voucher'];
}

// (2) Recompute authoritative cart totals server-side
$cart = get_cart();
if (empty($cart)) {
    temp('info', 'Your cart is empty');
    redirect('cart.php');
}

$items    = [];
$subtotal = 0.00;

$stm = $_db->prepare('SELECT * FROM product WHERE id = ?');
foreach ($cart as $id => $unit) {
    $stm->execute([$id]);
    $p = $stm->fetch();
    if (!$p) {
        continue;
    }
    $line      = product_price($p) * $unit;
    $items[]   = (object) ['product' => $p, 'unit' => $unit, 'subtotal' => $line, 'price' => product_price($p)];
    $subtotal += $line;
}

if (empty($items)) {
    set_cart();
    temp('info', 'Your cart is empty or products are no longer available');
    redirect('cart.php');
}

// Validate voucher properly against subtotal
$v_discount = $voucher ? voucher_discount($voucher, $subtotal) : 0;
$remaining  = $subtotal - $v_discount;

// Points validation
$available_points = (int) ($_SESSION['user']->points ?? 0);
if ($points === '' || $points === null) {
    $points = 0;
}
if (!ctype_digit((string) $points)) {
    $points = 0;
}
$points = (int) $points;
$rate = point_cash_rate();
$max_useful = (int) ceil($remaining / $rate);
$points_used = min($points, $max_useful, $available_points);
$p_discount = min(points_value($points_used), $remaining);

$discount = $v_discount + $p_discount;
$total    = max(0, $subtotal - $discount);

$_SESSION['pending_order'] = [
    'code' => $voucher ? $voucher->code : null,
    'points_used' => $points_used,
    'subtotal' => $subtotal,
    'discount' => $discount,
    'total' => $total,
];

cleanup_stale_pending_order_files(24 * 3600);

$pending_id = bin2hex(random_bytes(12));
$pending_file = pending_order_path($pending_id);
if (!$pending_file) {
    temp('info', 'Unable to start checkout');
    redirect('checkout.php');
}

$pending_payload = [
    'user_id' => $_user->id,
    'cart' => $cart,
    'code' => $voucher ? $voucher->code : null,
    'points_used' => $points_used,
    'subtotal' => $subtotal,
    'discount' => $discount,
    'total' => $total,
    'created' => time(),
];

file_put_contents($pending_file, json_encode($pending_payload));

// Zero-total checkout (fully covered by voucher/points) — no Stripe charge
if ($total <= 0) {
    $result = finalize_stripe_pending_order($pending_id, 'free');
    if (!$result['ok']) {
        temp('info', 'Checkout failed: ' . ($result['error'] ?? 'unknown'));
        redirect('checkout.php');
    }

    unset($_SESSION['pending_order']);
    set_cart();

    $points_used = (int) ($result['points_used'] ?? 0);
    $earned      = (int) ($result['earned'] ?? 0);
    $_SESSION['user']->points = ($_SESSION['user']->points ?? 0) - $points_used + $earned;
    $_SESSION['review_prompt_order'] = $result['order_id'];

    temp('info', 'Checkout successful');
    redirect('detail.php?id=' . $result['order_id']);
}

$stripe_secret = stripe_secret_key();
if (!$stripe_secret) {
    temp('info', 'Stripe secret key not configured. Set STRIPE_SECRET_KEY or create stripe_local.php.');
    redirect('checkout.php');
}

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'];
$origin = $scheme . '://' . $host;
$success = $origin . '/order/checkout_success.php?session_id={CHECKOUT_SESSION_ID}';
$cancel  = $origin . '/order/checkout.php';

$amount = (int) round($total * 100);

$post = [
    'payment_method_types[]' => 'card',
    'mode' => 'payment',
    'success_url' => $success,
    'cancel_url' => $cancel,
    'line_items[0][price_data][currency]' => 'myr',
    'line_items[0][price_data][product_data][name]' => 'Specialty Coffee & Tea Order',
    'line_items[0][price_data][unit_amount]' => $amount,
    'line_items[0][quantity]' => 1,
    'metadata[user_id]' => $_user->id,
    'metadata[pending_id]' => $pending_id,
];

$ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERPWD, $stripe_secret . ':');
$body = http_build_query($post);
curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
$res = curl_exec($ch);

if ($res === false) {
    $err = curl_error($ch);
    curl_close($ch);
    @file_put_contents(__DIR__ . '/stripe_debug.log', date('[Y-m-d H:i:s] ') . "request_failed: $err\n", FILE_APPEND);
    temp('info', 'Stripe request failed');
    redirect('checkout.php');
}

$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$json = json_decode($res);
@file_put_contents(__DIR__ . '/stripe_debug.log', date('[Y-m-d H:i:s] ') . "http_code=$http_code response=$res\n", FILE_APPEND);

if (!isset($json->url)) {
    temp('info', 'Stripe error: ' . ($json->error->message ?? 'Unknown'));
    redirect('checkout.php');
}

// Store session id on pending file for debugging/traceability
$pending_payload['stripe_session_id'] = $json->id ?? null;
file_put_contents($pending_file, json_encode($pending_payload));

header('Location: ' . $json->url);
exit;
