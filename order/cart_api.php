<?php
include '../_base.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_post()) {
    echo json_encode(['ok' => false, 'error' => 'Invalid request']);
    exit;
}

$action = req('action', 'update');
$id     = req('id');
$unit   = req('unit');

function cart_payload($message = '') {
    global $_db;

    $cart  = get_cart();
    $items = [];
    $count = 0;
    $total = 0.00;

    $stm = $_db->prepare('SELECT * FROM product WHERE id = ?');
    foreach ($cart as $pid => $u) {
        $stm->execute([$pid]);
        $p = $stm->fetch();
        if (!$p) continue;

        $price    = product_price($p);
        $subtotal = $price * $u;
        $count   += $u;
        $total   += $subtotal;
        $max      = min((int) $p->stock, 10);

        $items[] = [
            'id'       => $p->id,
            'name'     => $p->name,
            'photo'    => photo_url($p->photo),
            'price'    => round($price, 2),
            'price_fmt'=> sprintf('%.2f', $price),
            'list_price_fmt' => sprintf('%.2f', $p->price),
            'on_sale'  => is_on_sale($p),
            'unit'     => (int) $u,
            'max'      => $max,
            'subtotal' => round($subtotal, 2),
            'subtotal_fmt' => sprintf('%.2f', $subtotal),
            'stock'    => (int) $p->stock,
        ];
    }

    return [
        'ok'         => true,
        'message'    => $message,
        'empty'      => empty($items),
        'line_count' => count($items),
        'count'      => $count,
        'total'      => round($total, 2),
        'total_fmt'  => sprintf('%.2f', $total),
        'items'      => $items,
    ];
}

try {
    if ($action == 'clear') {
        set_cart();
        audit('Cart', 'Cleared cart', 'Cleared shopping cart via AJAX');
        echo json_encode(cart_payload('Cart cleared'));
        exit;
    }

    if ($action == 'preview') {
        // Checkout discount preview (voucher + points)
        auth('Member');
        $code   = req('code', '');
        $points = req('points', 0);
        $data   = cart_payload();

        $subtotal = $data['total'];
        $voucher  = null;
        $v_err    = null;

        if ($code != '') {
            $check = validate_voucher($code, $subtotal);
            if (!$check['ok']) {
                $v_err = $check['error'];
            }
            else {
                $voucher = $check['voucher'];
            }
        }

        $v_discount = voucher_discount($voucher, $subtotal);
        $remaining  = $subtotal - $v_discount;

        $available = get_user_points();
        if (!ctype_digit((string) $points)) $points = 0;
        $points = min((int) $points, $available);

        $rate        = point_cash_rate();
        $max_useful  = $rate > 0 ? (int) ceil($remaining / $rate) : 0;
        $points_used = min($points, $max_useful);
        $p_discount  = min(points_value($points_used), $remaining);
        $discount    = $v_discount + $p_discount;
        $total       = max($subtotal - $discount, 0);
        $earned      = points_earned($total);

        echo json_encode([
            'ok' => true,
            'subtotal'      => round($subtotal, 2),
            'subtotal_fmt'  => sprintf('%.2f', $subtotal),
            'v_discount'    => round($v_discount, 2),
            'v_discount_fmt'=> sprintf('%.2f', $v_discount),
            'p_discount'    => round($p_discount, 2),
            'p_discount_fmt'=> sprintf('%.2f', $p_discount),
            'discount'      => round($discount, 2),
            'discount_fmt'  => sprintf('%.2f', $discount),
            'total'         => round($total, 2),
            'total_fmt'     => sprintf('%.2f', $total),
            'points_used'   => $points_used,
            'points_earned' => $earned,
            'voucher_error' => $v_err,
            'voucher_ok'    => $voucher ? true : ($code == ''),
        ]);
        exit;
    }

    if ($id == '' || !is_exists($id, 'product', 'id')) {
        echo json_encode(['ok' => false, 'error' => 'Product not found']);
        exit;
    }

    if ($action == 'add') {
        $cart = get_cart();
        $curr = (int) ($cart[$id] ?? 0);
        $add  = ($unit === '' || $unit === null) ? 1 : (int) $unit;
        $new  = $curr + max(1, $add);
        update_cart($id, $new);
        audit('Cart', 'Added product to cart', "AJAX add product ID $id (qty $new)");
        echo json_encode(cart_payload('Added to cart'));
        exit;
    }

    if ($action == 'remove') {
        update_cart($id, 0);
        audit('Cart', 'Removed product from cart', "AJAX remove product ID $id");
        echo json_encode(cart_payload('Item removed'));
        exit;
    }

    // update (set exact qty)
    $unit = (int) $unit;
    update_cart($id, $unit);
    if ($unit <= 0) {
        audit('Cart', 'Removed product from cart', "AJAX removed product ID $id");
        echo json_encode(cart_payload('Item removed'));
    }
    else {
        audit('Cart', 'Updated cart quantity', "AJAX updated product ID $id to $unit");
        echo json_encode(cart_payload('Quantity updated'));
    }
}
catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
