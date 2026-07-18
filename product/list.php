<?php
include '../_base.php';

// ----------------------------------------------------------------------------

if (is_post()) {
    $id   = req('id');
    $unit = req('unit');
    audit('Cart', 'Added product to cart', "Added product ID $id with quantity $unit from product list page");
    update_cart($id, $unit);
    redirect();
}

$arr = $_db->query('SELECT * FROM product');

// ----------------------------------------------------------------------------

$_breadcrumbs = [
    'Dashboard' => '/',
    'Products' => '',
];
$_title = 'Product | List';
include '../_head.php';
?>

<div id="products">
    <?php foreach ($arr as $p): ?>
        <?php
        $cart     = get_cart();
        $id       = $p->id;
        $unit     = $cart[$p->id] ?? 0;
        $max_unit = min($p->stock, 10);
        $in_stock = $max_unit >= 1;
        ?>
        <div class="product">
            <div class="thumb">
                <span class="badge <?= $in_stock ? '' : 'out' ?>">
                    <?= $in_stock ? 'Available' : 'Not Available' ?>
                </span>
                <?php if ($unit): ?>
                    <span class="badge in-cart"><?= $unit ?> in cart</span>
                <?php endif ?>
                <img src="/photos/<?= $p->photo ?>"
                     alt="<?= encode($p->name) ?>"
                     data-get="/product/detail.php?id=<?= $p->id ?>">
            </div>

            <div class="info">
                <div class="name"><?= encode($p->name) ?></div>
                <div class="avail <?= $in_stock ? '' : 'out' ?>">
                    <?= $in_stock ? $p->stock . ' in stock' : 'Out of stock' ?>
                </div>
                <div class="price">RM <?= sprintf('%.2f', $p->price) ?></div>

                <?php if ($in_stock): ?>
                    <form method="post" class="actions">
                        <?= html_hidden('id', "id='id_$p->id'") ?>
                        <?php
                        $row_units = array_combine(range(1, $max_unit), range(1, $max_unit));
                        html_select('unit', $row_units, null, "id='unit_$p->id'");
                        ?>
                        <button type="submit">Add to Cart</button>
                    </form>
                <?php else: ?>
                    <div class="actions">
                        <button type="button" disabled style="opacity:.6;cursor:not-allowed;width:100%;">Sold Out</button>
                    </div>
                <?php endif ?>
            </div>
        </div>
    <?php endforeach ?>
</div>

<?php
include '../_foot.php';