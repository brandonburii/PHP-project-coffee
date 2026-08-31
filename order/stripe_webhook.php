<?php
include '../_base.php';

// Stripe webhook receiver. Configure your webhook endpoint in Stripe to
// point to /order/stripe_webhook.php and set STRIPE_WEBHOOK_SECRET.

$raw = file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
$webhook_secret = getenv('STRIPE_WEBHOOK_SECRET') ?: '';
if (!$webhook_secret) {
    http_response_code(400);
    echo 'Missing STRIPE_WEBHOOK_SECRET';
    exit;
}

// Verify signature (simple v1 verification)
$parts = explode(',', $sig_header);
$t = null;
$v1 = [];
foreach ($parts as $p) {
    [$k, $v] = explode('=', $p, 2) + [null, null];
    if ($k === 't') $t = $v;
    if ($k === 'v1') $v1[] = $v;
}
if (!$t || empty($v1)) {
    http_response_code(400);
    echo 'Invalid signature header';
    exit;
}
$signed_payload = $t . '.' . $raw;
$expected_sig = hash_hmac('sha256', $signed_payload, $webhook_secret);
$valid = false;
foreach ($v1 as $s) {
    if (hash_equals($expected_sig, $s)) { $valid = true; break; }
}
if (!$valid) {
    http_response_code(400);
    echo 'Signature verification failed';
    exit;
}

$event = json_decode($raw);
// Handle checkout.session.completed
if (isset($event->type) && $event->type === 'checkout.session.completed') {
    $session = $event->data->object;
    $pending_id = $session->metadata->pending_id ?? null;
    $pending_file = pending_order_path($pending_id);
    if ($pending_file && file_exists($pending_file)) {
            $data = json_decode(file_get_contents($pending_file), true);
            if (!empty($data) && empty($data['processed'])) {
                // Finalize order (same logic as checkout_success.php)
                try {
                    $_db->beginTransaction();
                    $vcode = $data['code'] ?? null;
                    $points_used = $data['points_used'] ?? 0;
                    $subtotal = $data['subtotal'];
                    $discount = $data['discount'];
                    $total = $data['total'];

                    $stm = $_db->prepare('
                        INSERT INTO `order`
                            (datetime, count, subtotal, discount, total, points_earned, points_used, voucher_code, user_id)
                        VALUES (NOW(), 0, 0, 0, 0, 0, 0, ?, ?)
                    ');
                    $stm->execute([$vcode, $data['user_id']]);
                    $order_id = $_db->lastInsertId();

                    $chk_count = 0;
                    $stm_prod   = $_db->prepare('SELECT * FROM product WHERE id = ? FOR UPDATE');
                    $stm_item   = $_db->prepare('INSERT INTO item (order_id, product_id, price, unit, subtotal) VALUES (?, ?, ?, ?, ?)');
                    $stm_deduct = $_db->prepare('UPDATE product SET stock = stock - ? WHERE id = ?');

                    foreach ($data['cart'] as $id => $unit) {
                        $stm_prod->execute([$id]);
                        $p = $stm_prod->fetch();
                        if ($p) {
                            if ($p->stock < $unit) throw new Exception("Insufficient stock for $id");
                            $unit_price = product_price($p);
                            $line = $unit_price * $unit;
                            $chk_count += $unit;
                            $stm_item->execute([$order_id, $id, $unit_price, $unit, $line]);
                            $stm_deduct->execute([$unit, $id]);
                            log_stock($id, 'sold', $p->stock, $p->stock - $unit);
                        }
                    }

                    $earned = points_earned($total);
                    $stm_update = $_db->prepare('
                        UPDATE `order`
                        SET count = ?, subtotal = ?, discount = ?, total = ?, points_earned = ?, points_used = ?
                        WHERE id = ?
                    ');
                    $stm_update->execute([$chk_count, $subtotal, $discount, $total, $earned, $points_used, $order_id]);

                    $stm_points = $_db->prepare('UPDATE user SET points = points - ? + ? WHERE id = ?');
                    $stm_points->execute([$points_used, $earned, $data['user_id']]);

                    if ($vcode) voucher_use($vcode);

                    $_db->commit();
                    // Idempotency: duplicate events find no pending file after first success.
                    delete_pending_order_file($pending_id);
                    http_response_code(200);
                    echo 'ok';
                    exit;
                }
                catch (Exception $e) {
                    $_db->rollBack();
                    http_response_code(500);
                    echo 'finalize failed';
                    exit;
                }
            }
    }
}

// For unhandled events, just respond 200
http_response_code(200);
echo 'ignored';
