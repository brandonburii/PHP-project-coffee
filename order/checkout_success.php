<?php
include '../_base.php';

auth('Member');

$session_id = req('session_id', '');
if (!$session_id) {
    temp('info', 'Missing Stripe session ID');
    redirect('checkout.php');
}

$stripe_secret = getenv('STRIPE_SECRET_KEY') ?: '';
if (!$stripe_secret) {
    temp('info', 'Stripe secret key not configured.');
    redirect('checkout.php');
}

// Retrieve Checkout Session
$ch = curl_init('https://api.stripe.com/v1/checkout/sessions/' . urlencode($session_id));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERPWD, $stripe_secret . ':');
$res = curl_exec($ch);
if ($res === false) {
    temp('info', 'Stripe request failed: ' . curl_error($ch));
    curl_close($ch);
    redirect('checkout.php');
}
curl_close($ch);

$json = json_decode($res);
if (!isset($json->payment_status) || $json->payment_status !== 'paid') {
    temp('info', 'Payment not completed.');
    redirect('checkout.php');
}

// Finalize order using pending session data
if (empty($_SESSION['pending_order'])) {
    temp('info', 'No pending order found in session.');
    redirect('cart.php');
}

$pending = $_SESSION['pending_order'];
$subtotal = $pending['subtotal'];
$discount = $pending['discount'];
$total    = $pending['total'];
$points_used = $pending['points_used'] ?? 0;
$vcode = $pending['code'] ?? null;

// DB transaction: insert order and items, update stock + points
$_db->beginTransaction();
try {
    $stm = $_db->prepare('
        INSERT INTO `order`
            (datetime, count, subtotal, discount, total, points_earned, points_used, voucher_code, user_id)
        VALUES (NOW(), 0, 0, 0, 0, 0, 0, ?, ?)
    ');
    $stm->execute([$vcode, $_user->id]);
    $order_id = $_db->lastInsertId();

    $chk_count = 0;

    $stm_prod   = $_db->prepare('SELECT * FROM product WHERE id = ? FOR UPDATE');
    $stm_item   = $_db->prepare('INSERT INTO item (order_id, product_id, price, unit, subtotal) VALUES (?, ?, ?, ?, ?)');
    $stm_deduct = $_db->prepare('UPDATE product SET stock = stock - ? WHERE id = ?');

    $cart = get_cart();
    foreach ($cart as $id => $unit) {
        $stm_prod->execute([$id]);
        $p = $stm_prod->fetch();
        if ($p) {
            if ($p->stock < $unit) {
                throw new Exception("Product '{$id}' is out of stock or has insufficient quantity (Available: {$p->stock})");
            }

            $unit_price = product_price($p);
            $line       = $unit_price * $unit;
            $chk_count += $unit;

            $stm_item->execute([$order_id, $id, $unit_price, $unit, $line]);
            $stm_deduct->execute([$unit, $id]);
            log_stock($id, 'sold', $p->stock, $p->stock - $unit);
        }
    }

    $stm_update = $_db->prepare('
        UPDATE `order`
        SET count = ?, subtotal = ?, discount = ?, total = ?, points_earned = ?, points_used = ?
        WHERE id = ?
    ');

    $earned = points_earned($total);
    $stm_update->execute([$chk_count, $subtotal, $discount, $total, $earned, $points_used, $order_id]);

    $stm_points = $_db->prepare('UPDATE user SET points = points - ? + ? WHERE id = ?');
    $stm_points->execute([$points_used, $earned, $_user->id]);

    if ($vcode) {
        voucher_use($vcode);
    }

    $_db->commit();

    audit('Orders', 'Checkout completed (Stripe)', "Order ID $order_id | subtotal: $subtotal, discount: $discount, total: $total, points used: $points_used, earned: $earned, voucher: " . ($vcode ?? '-'));

    $_SESSION['user']->points = ($_SESSION['user']->points ?? 0) - $points_used + $earned;

    // Clear pending order and cart
    unset($_SESSION['pending_order']);
    set_cart();

    temp('info', 'Checkout successful');
    redirect("detail.php?id=$order_id");
}
catch (Exception $e) {
    $_db->rollBack();
    temp('info', 'Finalizing order failed: ' . $e->getMessage());
    redirect('cart.php');
}