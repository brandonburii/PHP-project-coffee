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
                    log_stock($id, 'sold', $p->stock, $p->stock - $unit);
                }
            }

            $stm_update = $_db->prepare('
                UPDATE `order`
                SET count = ?, subtotal = ?, discount = ?, total = ?, points_earned = ?, points_used = ?
                WHERE id = ?
            ');
            $stm_update->execute([$chk_count, $subtotal, $discount, $total, $earned, $points_used, $order_id]);

            $stm_points = $_db->prepare('UPDATE user SET points = points - ? + ? WHERE id = ?');
            $stm_points->execute([$points_used, $earned, $_user->id]);

            if ($vcode) {
                voucher_use($vcode);
            }

            $_db->commit();

            audit('Orders', 'Checkout completed', "Order ID $order_id | subtotal: $subtotal, discount: $discount, total: $total, points used: $points_used, earned: $earned, voucher: " . ($vcode ?? '-'));

            $_SESSION['user']->points = $available_points - $points_used + $earned;

            set_cart();
            // Tell order detail page to show the review popup
            $_SESSION['review_prompt_order'] = $order_id;

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

<div class="checkout-layout">

    <div class="checkout-items">
        <div class="checkout-head">
            <h2>Your Order</h2>
            <a href="cart.php" class="checkout-edit">Edit cart →</a>
        </div>

        <div class="checkout-list">
            <?php foreach ($items as $it):
                $p = $it->product;
            ?>
            <article class="checkout-item">
                <img src="/photos/<?= photo_url($p->photo) ?>" alt="<?= encode($p->name) ?>">
                <div class="checkout-item-info">
                    <h3><?= encode($p->name) ?></h3>
                    <p>Qty <?= (int) $it->unit ?> · RM <?= sprintf('%.2f', $it->price) ?> each</p>
                </div>
                <div class="checkout-item-total">RM <?= sprintf('%.2f', $it->subtotal) ?></div>
            </article>
            <?php endforeach ?>
        </div>
    </div>

    <aside class="card checkout-pay">
        <h2>Payment</h2>

        <form method="post" class="form checkout-form" id="checkout-form" style="max-width:none;">
            <label for="code">Voucher Code</label>
            <?= html_text('code', 'maxlength="20" placeholder="e.g. WELCOME10" data-upper autocomplete="off"') ?>
            <span class="err" id="voucher-err"><?= $_err['code'] ?? '' ?></span>

            <label for="points">Redeem Points</label>
            <?= html_number('points', 0, $available_points, 1, 'placeholder="0"') ?>
            <?= err('points') ?>

            <section style="display:block;">
                <p class="checkout-hint">
                    You have <b><?= $available_points ?></b> point(s).
                    Earn <?= rtrim(rtrim(sprintf('%.2f', $pts_per_rm), '0'), '.') ?> pt per RM1.
                    1 point = RM <?= sprintf('%.2f', $cash_rate) ?>.
                </p>

                <div class="checkout-totals">
                    <div class="cart-summary-row">
                        <span>Subtotal (<?= $count ?> items)</span>
                        <span>RM <span id="preview-subtotal"><?= sprintf('%.2f', $subtotal) ?></span></span>
                    </div>
                    <div class="cart-summary-row discount">
                        <span>Discount</span>
                        <span>&minus; RM <span id="preview-discount"><?= sprintf('%.2f', $preview_vdiscount + $preview_pdiscount) ?></span></span>
                    </div>
                    <div class="cart-summary-row total">
                        <span>Total</span>
                        <span>RM <b id="preview-total"><?= sprintf('%.2f', max($preview_total, 0)) ?></b></span>
                    </div>
                    <div class="cart-summary-row muted">
                        <span>Points you will earn</span>
                        <span id="preview-earn"><?= points_earned(max($preview_total, 0)) ?></span>
                    </div>
                </div>

                <div class="checkout-actions">
                    <button type="submit" class="success" style="width:100%;">Confirm &amp; Place Order</button>
                    <button type="button" class="secondary" data-get="cart.php" style="width:100%;">Back to Cart</button>
                </div>
            </section>
        </form>
    </aside>
</div>

<script>
$(() => {
    let timer = null;

    function preview() {
        $.ajax({
            url: '/order/cart_api.php',
            method: 'POST',
            dataType: 'json',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            data: {
                action: 'preview',
                code: $('#code').val(),
                points: $('#points').val() || 0
            }
        }).done(res => {
            if (!res.ok) return;
            $('#preview-subtotal').text(res.subtotal_fmt);
            $('#preview-discount').text(res.discount_fmt);
            $('#preview-total').text(res.total_fmt);
            $('#preview-earn').text(res.points_earned);

            const $err = $('#voucher-err');
            if (res.voucher_error) {
                $err.text(res.voucher_error);
            } else {
                $err.text('');
            }
        });
    }

    $('#code, #points').on('input change', () => {
        clearTimeout(timer);
        timer = setTimeout(preview, 280);
    });
});
</script>

<?php include '../_foot.php';
