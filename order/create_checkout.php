<?php
include '../_base.php';

// Simple Stripe Checkout session creator (no composer required)
auth('Member');

if (!is_post()) redirect('checkout.php');

$code   = req('code', '');
$points = req('points', 0);

// (1) Basic validation: voucher
$voucher = null;
if ($code != '') {
    $check = validate_voucher($code, 0); // subtotal unknown here, validate more strictly below
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
    if (!$p) continue;
    $line      = product_price($p) * $unit;
    $items[]   = (object)['product' => $p, 'unit' => $unit, 'subtotal' => $line, 'price' => product_price($p)];
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
if ($points === '' || $points === null) $points = 0;
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

// Save pending order into session so we can finalize after payment
$_SESSION['pending_order'] = [
    'code' => $voucher ? $voucher->code : null,
    'points_used' => $points_used,
    'subtotal' => $subtotal,
    'discount' => $discount,
    'total' => $total,
];
// Also persist pending order to a server-side file so webhooks can finalize it

// Remove abandoned pending checkout files (24h+); failures are ignored.
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

// Stripe secret key
$stripe_secret = getenv('STRIPE_SECRET_KEY') ?: '';
if (!$stripe_secret) {
    temp('info', 'Stripe secret key not configured. Set STRIPE_SECRET_KEY.');
    redirect('checkout.php');
}

// Build absolute URLs for success/cancel
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$origin = $scheme . '://' . $host;
$success = $origin . '/order/checkout_success.php?session_id={CHECKOUT_SESSION_ID}';
$cancel  = $origin . '/order/checkout.php';

// Create Stripe Checkout Session via HTTP API
$amount = (int) round($total * 100); // in cents

$post = [
    'payment_method_types[]' => 'card',
    'mode' => 'payment',
    'success_url' => $success,
    'cancel_url'  => $cancel,
    'line_items[0][price_data][currency]' => 'myr',
    'line_items[0][price_data][product_data][name]' => $_settings->site_name ?? 'Order',
    'line_items[0][price_data][unit_amount]' => $amount,
    'line_items[0][quantity]' => 1,
    'metadata[user_id]' => $_user->id,
    'metadata[pending_id]' => $pending_id,
];

$ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERPWD, $stripe_secret . ':');
// Stripe expects application/x-www-form-urlencoded bodies, not multipart.
$body = http_build_query($post);
curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
$res = curl_exec($ch);
if ($res === false) {
    $err = curl_error($ch);
    curl_close($ch);
    // log and show friendly message
    @file_put_contents(__DIR__ . '/stripe_debug.log', date('[Y-m-d H:i:s] ')."request_failed: $err\n", FILE_APPEND);
    temp('info', 'Stripe request failed');
    redirect('checkout.php');
}

$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$json = json_decode($res);
// log Stripe response for debugging
@file_put_contents(__DIR__ . '/stripe_debug.log', date('[Y-m-d H:i:s] ')."http_code=$http_code response=$res\n", FILE_APPEND);

if (!isset($json->url)) {
    temp('info', 'Stripe error: ' . ($json->error->message ?? 'Unknown'));
    redirect('checkout.php');
}

// Redirect user to Stripe Checkout
header('Location: ' . $json->url);
exit;