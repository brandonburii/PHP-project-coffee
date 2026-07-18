<?php
include '../_base.php';

// ----------------------------------------------------------------------------

if (is_post()) {
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
$cart     = get_cart();
$unit     = $cart[$p->id] ?? 0;
$max_unit = min($p->stock, 10);
$in_stock = $max_unit >= 1;
?>

<div class="product-detail">
    <div class="gallery">
        <img src="/photos/<?= $p->photo ?>" alt="<?= encode($p->name) ?>">
    </div>

    <div class="pd-info">
        <h2><?= encode($p->name) ?></h2>
        <div class="pd-price">RM <?= sprintf('%.2f', $p->price) ?></div>

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
            <div class="row">
                <span class="label">Availability</span>
                <span><?= $in_stock ? $p->stock . ' unit(s) in stock' : 'Currently unavailable' ?></span>
            </div>
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

        <p style="margin-top: 22px;">
            <button class="secondary" data-get="list.php">&larr; Back to Products</button>
        </p>
    </div>
</div>

<?php
include '../_foot.php';