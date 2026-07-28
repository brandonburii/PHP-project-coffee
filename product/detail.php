<?php
include '../_base.php';

// ----------------------------------------------------------------------------

if (is_post()) {
    $btn = req('btn');

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
    audit('Cart', 'Added product to cart', "Added product ID $id with quantity $unit from product detail page");
    update_cart($id, $unit);
    redirect();
}

$id  = req('id');
$stm = $_db->prepare('SELECT * FROM product WHERE id = ?');
$stm->execute([$id]);
$p = $stm->fetch();
if (!$p) redirect('list.php');

audit('Products', 'Viewed Product', "Viewed product ID: {$p->id}, Name: {$p->name}");
add_recent($p->id);

// Customers also bought — products frequently in the same orders
$stm = $_db->prepare('
    SELECT p.*, COUNT(*) AS bought_together
    FROM item i1
    JOIN item i2 ON i1.order_id = i2.order_id AND i2.product_id != i1.product_id
    JOIN product p ON p.id = i2.product_id
    WHERE i1.product_id = ? AND p.stock > 0
    GROUP BY p.id
    ORDER BY bought_together DESC
    LIMIT 4
');
$stm->execute([$p->id]);
$also_bought = $stm->fetchAll();

// Fallback: other in-stock products (excluding current) if no joint purchases yet
if (empty($also_bought)) {
    $stm = $_db->prepare('
        SELECT * FROM product
        WHERE id != ? AND stock > 0
        ORDER BY id
        LIMIT 4
    ');
    $stm->execute([$p->id]);
    $also_bought = $stm->fetchAll();
}

// ----------------------------------------------------------------------------

$_breadcrumbs = [
    'Dashboard' => '/',
    'Products' => 'list.php',
    'Product Detail' => '',
];
$_title = 'Product | Detail';
include '../_head.php';
?>

<?php
$cart       = get_cart();
$unit       = $cart[$p->id] ?? 0;
$max_unit   = min($p->stock, 10);
$in_stock   = $max_unit >= 1;
$on_sale    = is_on_sale($p);
$price      = product_price($p);
$in_compare = in_array($p->id, get_compare());
?>

<div class="product-detail">
    <div class="gallery">
        <img src="/photos/<?= $p->photo ?>" alt="<?= encode($p->name) ?>">
    </div>

    <div class="pd-info">
        <h2>
            <?= encode($p->name) ?>
            <?php if (!empty($p->tag)): ?>
                <span class="badge-status process"><?= encode($p->tag) ?></span>
            <?php endif ?>
            <?php if ($on_sale): ?>
                <span class="badge-status danger">Flash Sale</span>
            <?php endif ?>
        </h2>

        <div class="pd-price">
            <?php if ($on_sale): ?>
                <span style="text-decoration:line-through; color:var(--muted); font-size:1rem; font-weight:500; margin-right:8px;">RM <?= sprintf('%.2f', $p->price) ?></span>
            <?php endif ?>
            RM <?= sprintf('%.2f', $price) ?>
        </div>

        <p>
            <span class="badge-status <?= $in_stock ? 'success' : 'danger' ?>">
                <?= $in_stock ? 'In Stock' : 'Out of Stock' ?>
            </span>
        </p>

        <?php if (!empty($p->description)): ?>
            <p style="color: var(--muted); line-height: 1.6;"><?= encode($p->description) ?></p>
        <?php endif ?>

        <div class="pd-meta">
            <div class="row">
                <span class="label">Product ID</span>
                <span><?= encode($p->id) ?></span>
            </div>
            <?php if (!empty($p->origin)): ?>
            <div class="row">
                <span class="label">Origin</span>
                <span><?= encode($p->origin) ?></span>
            </div>
            <?php endif ?>
            <?php if (!empty($p->roast)): ?>
            <div class="row">
                <span class="label">Roast</span>
                <span><?= encode($p->roast) ?></span>
            </div>
            <?php endif ?>
            <div class="row">
                <span class="label">Availability</span>
                <span><?= $in_stock ? $p->stock . ' unit(s) in stock' : 'Currently unavailable' ?></span>
            </div>
            <?php if ($on_sale): ?>
            <div class="row">
                <span class="label">Sale Ends</span>
                <span><?= date('Y-m-d H:i', strtotime($p->sale_end)) ?></span>
            </div>
            <?php endif ?>
        </div>

        <?php if ($in_stock): ?>
            <form method="post" class="pd-buy">
                <?= html_hidden('id', "id='id_$p->id'") ?>
                <?php
                $row_units = array_combine(range(1, $max_unit), range(1, $max_unit));
                html_select('unit', $row_units, null, "id='unit_$p->id'");
                ?>
                <button type="submit"><?= icon('cart') ?>Add to Cart</button>
                <?php if ($unit): ?>
                    <span class="badge-status success"><?= $unit ?> in cart</span>
                <?php endif ?>
            </form>
        <?php else: ?>
            <div class="pd-buy">
                <button type="button" disabled>Sold Out</button>
            </div>
        <?php endif ?>

        <form method="post" style="margin-top:12px;">
            <input type="hidden" name="btn" value="compare">
            <input type="hidden" name="id" value="<?= encode($p->id) ?>">
            <button type="submit" class="secondary">
                <?= $in_compare ? '✓ Remove from Compare' : '+ Add to Compare' ?>
            </button>
        </form>

        <p style="margin-top: 22px;">
            <button class="secondary" data-get="list.php">&larr; Back to Products</button>
            <?php if (get_compare()): ?>
                <button data-get="compare.php">View Compare (<?= count(get_compare()) ?>)</button>
            <?php endif ?>
        </p>
    </div>
</div>

<?php if (!empty($also_bought)): ?>
    <h2 style="margin-top: 40px;">Customers Also Bought</h2>
    <div id="products">
        <?php foreach ($also_bought as $ap): ?>
            <?php $on_sale = is_on_sale($ap); ?>
            <div class="product">
                <div class="thumb">
                    <?php if (!empty($ap->tag)): ?>
                        <span class="badge" style="background:var(--coffee);"><?= encode($ap->tag) ?></span>
                    <?php endif ?>
                    <?php if ($on_sale): ?>
                        <span class="badge" style="left:auto; right:10px; background:var(--red);">SALE</span>
                    <?php endif ?>
                    <img src="/photos/<?= $ap->photo ?>"
                         alt="<?= encode($ap->name) ?>"
                         data-get="/product/detail.php?id=<?= $ap->id ?>">
                </div>
                <div class="info">
                    <div class="name"><?= encode($ap->name) ?></div>
                    <div class="price">RM <?= sprintf('%.2f', product_price($ap)) ?></div>
                    <button class="secondary" data-get="/product/detail.php?id=<?= $ap->id ?>" style="width:100%;">View</button>
                </div>
            </div>
        <?php endforeach ?>
    </div>
<?php endif ?>

<?php
include '../_foot.php';
