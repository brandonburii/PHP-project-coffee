<?php
include '../_base.php';

// ----------------------------------------------------------------------------

// (1) Authorization (member)
auth('Member');

// (2) Get shopping cart (reject if empty)
$cart = get_cart();
if (empty($cart)) {
    redirect('cart.php');
}

// (3) Build authoritative order summary from the cart (server-side)
$items    = [];
$subtotal = 0.00;
$count    = 0;

$stm = $_db->prepare('SELECT * FROM product WHERE id = ?');
foreach ($cart as $id => $unit) {
    $stm->execute([$id]);
    $p = $stm->fetch();
    if (!$p) continue;

    $line      = product_price($p) * $unit;
    $items[]   = (object)['product' => $p, 'unit' => $unit, 'subtotal' => $line, 'price' => product_price($p)];
    $subtotal += $line;
    $count    += $unit;
}

// Cart only had deleted/missing products — clean up and go back
if (empty($items)) {
    set_cart();
    temp('info', 'Your cart is empty or products are no longer available');
    redirect('cart.php');
}

// (4) Current reward points (fresh from DB)
$stm = $_db->prepare('SELECT points FROM user WHERE id = ?');
$stm->execute([$_user->id]);
$available_points = (int) $stm->fetchColumn();

// Form values (kept across validation errors)
$code   = req('code', '');
$points = req('points', 0);

if (is_post()) {
    // Validate: voucher (optional)
    $voucher = null;
    if ($code != '') {
        $check = validate_voucher($code, $subtotal);
        if (!$check['ok']) {
            $_err['code'] = $check['error'];
        }
        else {
            $voucher = $check['voucher'];
        }
    }

    // Validate: points to redeem (optional)
    if ($points === '' || $points === null) {
        $points = 0;
    }
    if (!ctype_digit((string) $points)) {
        $_err['points'] = 'Must be a whole number';
    }
    else {
        $points = (int) $points;
        if ($points > $available_points) {
            $_err['points'] = "You only have $available_points point(s)";
        }
    }

    if (!$_err) {
        // (A) Compute discounts: voucher first, then points on the remainder
        $v_discount = voucher_discount($voucher, $subtotal);
        $remaining  = $subtotal - $v_discount;

        // Only use as many points as are useful (never discount below 0)
        $rate        = point_cash_rate();
        $max_useful  = (int) ceil($remaining / $rate);
        $points_used = min($points, $max_useful);
        $p_discount  = min(points_value($points_used), $remaining);

        $discount = $v_discount + $p_discount;
        $total    = $subtotal - $discount;
        $earned   = points_earned($total);
        $vcode    = $voucher ? $voucher->code : null;

        // (B) DB transaction (insert order and items, update stock + points)
        $_db->beginTransaction();

        try {
            // Insert order (values filled in after computing items)
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

                    // Stock history: products sold
                    log_stock($id, 'sold', $p->stock, $p->stock - $unit);
                }
            }

            // Update order totals
            $stm_update = $_db->prepare('
                UPDATE `order`
                SET count = ?, subtotal = ?, discount = ?, total = ?, points_earned = ?, points_used = ?
                WHERE id = ?
            ');
            $stm_update->execute([$chk_count, $subtotal, $discount, $total, $earned, $points_used, $order_id]);

            // Update member points balance (deduct used, add earned)
            $stm_points = $_db->prepare('UPDATE user SET points = points - ? + ? WHERE id = ?');
            $stm_points->execute([$points_used, $earned, $_user->id]);

            // Increment voucher usage after successful order
            if ($vcode) {
                voucher_use($vcode);
            }

            $_db->commit();

            audit('Orders', 'Checkout completed', "Order ID $order_id | subtotal: $subtotal, discount: $discount, total: $total, points used: $points_used, earned: $earned, voucher: " . ($vcode ?? '-'));

            // Keep the session user's points in sync
            $_SESSION['user']->points = $available_points - $points_used + $earned;

            // Clear cart + redirect to detail
            set_cart();
            temp('info', 'Checkout successful');
            redirect("detail.php?id=$order_id");
        }
        catch (Exception $e) {
            $_db->rollBack();
            temp('info', 'Checkout failed: ' . $e->getMessage());
            redirect('cart.php');
        }
    }
}

// ----------------------------------------------------------------------------

// Preview discount for the review screen (based on current inputs)
$preview_voucher   = ($code != '') ? get_valid_voucher($code, $subtotal) : null;
$preview_vdiscount = voucher_discount($preview_voucher, $subtotal);
$preview_remaining = $subtotal - $preview_vdiscount;
$preview_points    = ctype_digit((string) $points) ? min((int) $points, $available_points) : 0;
$preview_pdiscount = min(points_value($preview_points), $preview_remaining);
$preview_total     = $subtotal - $preview_vdiscount - $preview_pdiscount;
$cash_rate         = point_cash_rate();
$pts_per_rm        = points_rate();

$_breadcrumbs = [
    'Dashboard'     => '/',
    'Shopping Cart' => 'cart.php',
    'Checkout'      => '',
];
$_title = 'Order | Checkout';
include '../_head.php';
?>

<div style="display: grid; grid-template-columns: 1.4fr 1fr; gap: 26px; align-items: start;">

    <!-- Order items -->
    <div>
        <h2 style="margin-top: 0;">Order Summary</h2>
        <table class="table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th class="right">Price (RM)</th>
                    <th class="right">Qty</th>
                    <th class="right">Subtotal (RM)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $it): ?>
                <tr>
                    <td><?= encode($it->product->name) ?></td>
                    <td class="right"><?= sprintf('%.2f', $it->price ?? product_price($it->product)) ?></td>
                    <td class="right"><?= $it->unit ?></td>
                    <td class="right"><?= sprintf('%.2f', $it->subtotal) ?></td>
                </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>

    <!-- Payment / discounts -->
    <div class="card">
        <h2 style="margin-top: 0;">Payment</h2>

        <form method="post" class="form" style="max-width: none;">
            <label for="code">Voucher Code</label>
            <?= html_text('code', 'maxlength="20" placeholder="e.g. WELCOME10" data-upper') ?>
            <?= err('code') ?>

            <label for="points">Redeem Points</label>
            <?= html_number('points', 0, $available_points, 1, 'placeholder="0"') ?>
            <?= err('points') ?>

            <section style="display:block;">
                <p style="color: var(--muted); font-size: .85rem; margin: 4px 0 14px;">
                    You have <b><?= $available_points ?></b> point(s).
                    Earn <?= rtrim(rtrim(sprintf('%.2f', $pts_per_rm), '0'), '.') ?> pt per RM1 spent.
                    1 point = RM <?= sprintf('%.2f', $cash_rate) ?> at checkout.
                </p>

                <div style="display:flex; flex-direction:column; gap:8px; border-top:1px solid var(--line); padding-top:14px;">
                    <div style="display:flex; justify-content:space-between;">
                        <span>Subtotal</span>
                        <span>RM <?= sprintf('%.2f', $subtotal) ?></span>
                    </div>
                    <div style="display:flex; justify-content:space-between; color: var(--green);">
                        <span>Discount</span>
                        <span>&minus; RM <?= sprintf('%.2f', $preview_vdiscount + $preview_pdiscount) ?></span>
                    </div>
                    <div style="display:flex; justify-content:space-between; font-weight:700; font-size:1.15rem; color: var(--coffee); border-top:1px solid var(--line); padding-top:8px;">
                        <span>Total</span>
                        <span>RM <?= sprintf('%.2f', max($preview_total, 0)) ?></span>
                    </div>
                </div>

                <div style="display:flex; gap:10px; margin-top:18px;">
                    <button type="submit" class="success">Confirm &amp; Place Order</button>
                    <button type="button" class="secondary" data-get="cart.php">Back to Cart</button>
                </div>
            </section>
        </form>
    </div>
</div>

<?php
include '../_foot.php';
