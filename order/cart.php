<?php
include '../_base.php';

// Keep classic POST as fallback (non-JS)
if (is_post() && !isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    $btn = req('btn');
    if ($btn == 'clear') {
        audit('Cart', 'Removed product from cart', 'Cleared shopping cart');
        set_cart();
        redirect('?');
    }

    $id   = req('id');
    $unit = req('unit');
    if ($unit <= 0) {
        audit('Cart', 'Removed product from cart', "Removed product ID $id from cart");
    } else {
        audit('Cart', 'Updated cart quantity', "Updated product ID $id quantity to $unit in cart");
    }
    update_cart($id, $unit);
    redirect();
}

$_breadcrumbs = [
    'Dashboard' => '/',
    'Shopping Cart' => '',
];
$_title = 'Order | Shopping Cart';
include '../_head.php';

$cart  = get_cart();
$items = [];
$count = 0;
$total = 0.00;

$stm = $_db->prepare('SELECT * FROM product WHERE id = ?');
foreach ($cart as $id => $unit) {
    $stm->execute([$id]);
    $p = $stm->fetch();
    if (!$p) continue;

    $price    = product_price($p);
    $subtotal = $price * $unit;
    $count   += $unit;
    $total   += $subtotal;
    $items[]  = (object)[
        'product'  => $p,
        'unit'     => $unit,
        'price'    => $price,
        'subtotal' => $subtotal,
        'max'      => min((int) $p->stock, 10),
    ];
}
?>

<div id="cart-page">
<?php if (empty($items)): ?>
    <div class="empty-state" id="cart-empty">
        <span class="emoji">🛒</span>
        <p class="title">Your cart is empty</p>
        <p class="hint">Browse our coffee &amp; tea selection and add something you love.</p>
        <button data-get="/product/list.php">Browse Products</button>
    </div>
<?php else: ?>
    <div class="cart-layout" id="cart-content">
        <div class="cart-items" id="cart-items">
            <?php foreach ($items as $it):
                $p = $it->product;
            ?>
            <article class="cart-item" data-id="<?= encode($p->id) ?>">
                <a class="cart-item-img" href="/product/detail.php?id=<?= urlencode($p->id) ?>">
                    <img src="/photos/<?= photo_url($p->photo) ?>" alt="<?= encode($p->name) ?>">
                </a>
                <div class="cart-item-body">
                    <div class="cart-item-top">
                        <div>
                            <h3><?= encode($p->name) ?></h3>
                            <p class="cart-item-meta">ID <?= encode($p->id) ?></p>
                        </div>
                        <button type="button" class="cart-remove" data-cart-remove="<?= encode($p->id) ?>" title="Remove">✕</button>
                    </div>
                    <div class="cart-item-bottom">
                        <div class="cart-price">
                            <?php if (is_on_sale($p)): ?>
                                <span class="price-was">RM <?= sprintf('%.2f', $p->price) ?></span>
                            <?php endif ?>
                            RM <span class="js-unit-price"><?= sprintf('%.2f', $it->price) ?></span>
                        </div>
                        <div class="qty-control" data-id="<?= encode($p->id) ?>" data-max="<?= $it->max ?>">
                            <button type="button" class="qty-btn" data-qty="-1" aria-label="Decrease">−</button>
                            <input type="number" class="qty-input" value="<?= (int) $it->unit ?>" min="1" max="<?= $it->max ?>" readonly>
                            <button type="button" class="qty-btn" data-qty="1" aria-label="Increase">+</button>
                        </div>
                        <div class="cart-line-total">
                            RM <span class="js-line-total"><?= sprintf('%.2f', $it->subtotal) ?></span>
                        </div>
                    </div>
                </div>
            </article>
            <?php endforeach ?>
        </div>

        <aside class="cart-summary card">
            <h2>Order Summary</h2>
            <div class="cart-summary-row">
                <span>Items</span>
                <span id="cart-count"><?= $count ?></span>
            </div>
            <div class="cart-summary-row total">
                <span>Total</span>
                <span>RM <b id="cart-total"><?= sprintf('%.2f', $total) ?></b></span>
            </div>
            <div class="cart-summary-actions">
                <?php if ($_user?->role == 'Member'): ?>
                    <button class="success" data-get="checkout.php" style="width:100%;">Checkout</button>
                <?php else: ?>
                    <p style="color:var(--muted); font-size:.9rem;">Please <a href="/login.php">login</a> as member to checkout.</p>
                <?php endif ?>
                <button class="danger" id="cart-clear" style="width:100%;">Clear Cart</button>
                <button class="secondary" data-get="/product/list.php" style="width:100%;">Continue Shopping</button>
            </div>
        </aside>
    </div>
<?php endif ?>
</div>

<script>
$(() => {
    const api = '/order/cart_api.php';

    function toast(msg) {
        if (!msg) return;
        let $t = $('#cart-toast');
        if (!$t.length) {
            $t = $('<div id="cart-toast" class="cart-toast"></div>').appendTo('body');
        }
        $t.text(msg).addClass('show');
        clearTimeout(window._cartToast);
        window._cartToast = setTimeout(() => $t.removeClass('show'), 1800);
    }

    function updateNavBadge(lineCount) {
        const $link = $('nav a[href="/order/cart.php"]');
        if (!$link.length) return;
        let $b = $link.find('.nav-badge');
        if (lineCount > 0) {
            if (!$b.length) $b = $('<span class="nav-badge"></span>').appendTo($link);
            $b.text(lineCount);
        } else {
            $b.remove();
        }
    }

    function showEmpty() {
        $('#cart-page').html(
            '<div class="empty-state" id="cart-empty">' +
            '<span class="emoji">🛒</span>' +
            '<p class="title">Your cart is empty</p>' +
            '<p class="hint">Browse our coffee &amp; tea selection and add something you love.</p>' +
            '<button data-get="/product/list.php">Browse Products</button>' +
            '</div>'
        );
        $('[data-get]').off('click').on('click', e => {
            e.preventDefault();
            location = e.currentTarget.dataset.get || location;
        });
    }

    function applyCart(data) {
        updateNavBadge(data.line_count);
        if (data.empty) {
            showEmpty();
            return;
        }

        $('#cart-count').text(data.count);
        $('#cart-total').text(data.total_fmt);

        // Remove rows not in response
        const ids = data.items.map(i => String(i.id));
        $('.cart-item').each(function () {
            if (!ids.includes(String($(this).data('id')))) {
                $(this).slideUp(180, function () { $(this).remove(); });
            }
        });

        data.items.forEach(item => {
            const $row = $(`.cart-item[data-id="${item.id}"]`);
            if (!$row.length) return;
            $row.find('.qty-input').val(item.unit);
            $row.find('.qty-control').attr('data-max', item.max);
            $row.find('.js-unit-price').text(item.price_fmt);
            $row.find('.js-line-total').text(item.subtotal_fmt);
        });
    }

    function cartAjax(payload, done) {
        $.ajax({
            url: api,
            method: 'POST',
            data: payload,
            dataType: 'json',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).done(res => {
            if (!res.ok) {
                toast(res.error || 'Something went wrong');
                return;
            }
            applyCart(res);
            if (res.message) toast(res.message);
            if (done) done(res);
        }).fail(() => toast('Network error'));
    }

    // Qty +/- 
    $(document).on('click', '.qty-btn', function () {
        const $ctrl = $(this).closest('.qty-control');
        const id = $ctrl.data('id');
        const max = parseInt($ctrl.data('max'), 10) || 10;
        const delta = parseInt($(this).data('qty'), 10);
        let unit = parseInt($ctrl.find('.qty-input').val(), 10) || 1;
        unit = Math.min(max, Math.max(1, unit + delta));
        $ctrl.find('.qty-input').val(unit);
        cartAjax({ action: 'update', id, unit });
    });

    // Remove item
    $(document).on('click', '[data-cart-remove]', function () {
        const id = $(this).data('cart-remove');
        showConfirm('Remove this item?', 'It will be removed from your cart.', () => {
            cartAjax({ action: 'remove', id });
        });
    });

    // Clear cart
    $(document).on('click', '#cart-clear', function () {
        showConfirm('Clear your cart?', 'This action cannot be undone.', () => {
            cartAjax({ action: 'clear' });
        });
    });
});
</script>

<?php include '../_foot.php';
