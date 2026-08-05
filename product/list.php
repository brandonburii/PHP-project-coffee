<?php
include '../_base.php';

// ----------------------------------------------------------------------------

if (is_post()) {
    $btn = req('btn');

    // Toggle compare selection
    if ($btn == 'compare') {
        $id = req('id');
        if ($id != '' && is_exists($id, 'product', 'id')) {
            if (!toggle_compare($id)) {
                temp('info', 'You can compare up to 3 products only');
            }
        }
        redirect();
    }

    $id   = req('id');
    $unit = req('unit');

    // Block add-to-cart when out of stock
    $stm = $_db->prepare('SELECT stock FROM product WHERE id = ?');
    $stm->execute([$id]);
    $stock = (int) $stm->fetchColumn();
    if ($stock < 1) {
        temp('info', 'This product is out of stock');
        redirect();
    }

    audit('Cart', 'Added product to cart', "Added product ID $id with quantity $unit from product list page");
    update_cart($id, $unit);
    redirect();
}

$arr = $_db->query('SELECT * FROM product');
$compare = get_compare();

// ----------------------------------------------------------------------------

$_breadcrumbs = [
    'Dashboard' => '/',
    'Products' => '',
];
$_title = 'Product | List';
include '../_head.php';
?>

<?php if ($compare): ?>
<div class="card" style="margin-bottom:18px; display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;">
    <div>
        <b><?= count($compare) ?></b> product(s) selected for comparison
        <span style="color:var(--muted); font-size:.85rem;">(max 3)</span>
    </div>
    <button data-get="compare.php">Compare Now</button>
</div>
<?php endif ?>

<div id="products">
    <?php foreach ($arr as $p): ?>
        <?php
        $cart     = get_cart();
        $id       = $p->id;
        $unit     = $cart[$p->id] ?? 0;
        $max_unit = min($p->stock, 10);
        $in_stock = $max_unit >= 1;
        $on_sale  = is_on_sale($p);
        $price    = product_price($p);
        $in_compare = in_array($p->id, $compare);
        $img      = photo_url($p->photo);
        ?>
        <div class="product <?= $in_stock ? '' : 'is-soldout' ?>">
            <div class="thumb">
                <?php if ($unit): ?>
                    <span class="badge in-cart"><?= $unit ?> in cart</span>
                <?php endif ?>
                <?php if (!empty($p->tag)): ?>
                    <span class="badge tag-badge"><?= encode($p->tag) ?></span>
                <?php endif ?>
                <?php if ($on_sale && $in_stock): ?>
                    <span class="badge sale-badge">SALE</span>
                <?php endif ?>
                <img src="/photos/<?= $img ?>"
                     alt="<?= encode($p->name) ?>"
                     data-get="/product/detail.php?id=<?= $p->id ?>">
            </div>

            <div class="info">
                <div class="name"><?= encode($p->name) ?></div>
                <?php if (!empty($p->origin) || !empty($p->roast)): ?>
                    <div class="meta-line">
                        <?= encode(trim(($p->origin ?? '') . (!empty($p->origin) && !empty($p->roast) ? ' · ' : '') . ($p->roast ?? ''))) ?>
                    </div>
                <?php endif ?>

                <div class="price-row">
                    <div class="price">
                        <?php if ($on_sale && $in_stock): ?>
                            <span class="price-was">RM <?= sprintf('%.2f', $p->price) ?></span>
                        <?php endif ?>
                        RM <?= sprintf('%.2f', $price) ?>
                    </div>
                    <span class="avail <?= $in_stock ? '' : 'out' ?>">
                        <?= $in_stock ? $p->stock . ' available' : 'Unavailable' ?>
                    </span>
                </div>

                <?php if ($in_stock): ?>
                    <form method="post" class="actions ajax-cart">
                        <?= html_hidden('id', "id='id_$p->id'") ?>
                        <?php
                        $row_units = array_combine(range(1, $max_unit), range(1, $max_unit));
                        html_select('unit', $row_units, null, "id='unit_$p->id'");
                        ?>
                        <button type="submit">Add to Cart</button>
                    </form>
                <?php else: ?>
                    <div class="actions">
                        <select disabled aria-label="Quantity unavailable">
                            <option>0</option>
                        </select>
                        <button type="button" disabled>Sold Out</button>
                    </div>
                <?php endif ?>

                <form method="post" style="margin-top:6px;">
                    <input type="hidden" name="btn" value="compare">
                    <input type="hidden" name="id" value="<?= encode($p->id) ?>">
                    <button type="submit" class="secondary" style="width:100%; font-size:.8rem;">
                        <?= $in_compare ? '✓ In Compare' : '+ Compare' ?>
                    </button>
                </form>
            </div>
        </div>
    <?php endforeach ?>
</div>

<?php
include '../_foot.php';
