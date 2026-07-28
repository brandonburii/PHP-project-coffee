<?php
include '../_base.php';

// ----------------------------------------------------------------------------

if (is_post()) {
    $btn = req('btn');
    if ($btn == 'clear') {
        audit('Cart', 'Removed product from cart', "Cleared shopping cart");
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

// ----------------------------------------------------------------------------

$_breadcrumbs = [
    'Dashboard' => '/',
    'Shopping Cart' => '',
];
$_title = 'Order | Shopping Cart';
include '../_head.php';
?>

<style>
    .popup {
        width: 100px;
        height: 100px;
    }
</style>

<?php $cart = get_cart(); ?>

<?php if (empty($cart)): ?>
    <div class="empty-state">
        <span class="emoji">🛒</span>
        <p class="title">Your cart is empty</p>
        <p class="hint">Browse our coffee &amp; tea selection and add something you love.</p>
        <button data-get="/product/list.php">Browse Products</button>
    </div>
<?php else: ?>
<table class="table">
    <tr>
        <th>Id</th>
        <th>Name</th>
        <th>Price (RM)</th>
        <th>Unit</th>
        <th>Subtotal (RM)</th>
    </tr>

    <?php
        $count = 0;
        $total = 0; 
        
        $stm = $_db->prepare('SELECT * FROM product WHERE id = ?');
        $cart = get_cart();
        
        foreach ($cart as $id => $unit):
            $stm->execute([$id]);
            $p = $stm->fetch();
            if (!$p) continue;

            $subtotal = product_price($p) * $unit;
            $count += $unit;
            $total += $subtotal; 
    ?>
        <tr>
            <td><?= $p->id ?></td>
            <td><?= $p->name ?></td>
            <td class="right">
                <?php if (is_on_sale($p)): ?>
                    <span style="text-decoration:line-through; color:var(--muted); font-size:.85rem;"><?= sprintf('%.2f', $p->price) ?></span>
                    <?= sprintf('%.2f', product_price($p)) ?>
                <?php else: ?>
                    <?= sprintf('%.2f', $p->price) ?>
                <?php endif ?>
            </td>
            <td>
                <form method="post">
                    <?= html_hidden('id', "id='id_$p->id'") ?>
                    <?php
                    $max_unit = min($p->stock, 10);
                    $row_units = $max_unit >= 1 ? array_combine(range(1, $max_unit), range(1, $max_unit)) : [];
                    html_select('unit', $row_units, '', "id='unit_$p->id'");
                    ?>
                </form>            
            </td>
            <td class="right">
                <?= sprintf('%.2f', $subtotal) ?>
                <img src="/photos/<?= $p->photo ?>" class="popup">
            </td>
        </tr>
    <?php endforeach ?>

    <tr>
        <th colspan="3"></th>
        <th class="right"><?= $count ?></th>
        <th class="right"><?= sprintf('%.2f', $total) ?></th>
    </tr>
</table>

<p>
    <button class="danger" data-post="?btn=clear" data-confirm="Clear your cart?&#10;This action cannot be undone.">Clear Cart</button>

    <?php if ($_user?->role == 'Member'): ?>
        <button class="success" data-get="checkout.php">Checkout</button>
    <?php else: ?>
        Please <a href="/login.php">login</a> as member to checkout
    <?php endif ?>
</p>
<?php endif ?>

<script>
    $('select').on('change', e => e.target.form.submit());
</script>

<?php
include '../_foot.php';