<?php
include '../_base.php';

// ----------------------------------------------------------------------------

if (is_post()) {
    $btn = req('btn');

    if ($btn == 'clear') {
        $_SESSION['compare'] = [];
        temp('info', 'Comparison cleared');
        redirect('list.php');
    }

    if ($btn == 'remove') {
        $id = req('id');
        toggle_compare($id); // removes if present
        redirect();
    }
}

$ids = get_compare();
$products = [];

if ($ids) {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stm = $_db->prepare("SELECT * FROM product WHERE id IN ($placeholders)");
    $stm->execute(array_values($ids));
    $fetched = $stm->fetchAll();

    // Keep selection order
    $map = [];
    foreach ($fetched as $p) {
        $map[$p->id] = $p;
    }
    foreach ($ids as $id) {
        if (isset($map[$id])) {
            $products[] = $map[$id];
        }
    }
}

// ----------------------------------------------------------------------------

$_breadcrumbs = [
    'Dashboard' => '/',
    'Products' => 'list.php',
    'Compare' => '',
];
$_title = 'Product | Compare';
include '../_head.php';
?>

<?php if (count($products) < 2): ?>
    <div class="empty-state">
        <span class="emoji">⚖️</span>
        <p class="title">Select at least 2 products to compare</p>
        <p class="hint">Use the Compare button on the product list or detail page (max 3).</p>
        <button data-get="list.php">Browse Products</button>
    </div>
<?php else: ?>

<p>
    <button class="danger" data-post="?btn=clear" data-confirm="Clear comparison list?">Clear Compare</button>
    <button class="secondary" data-get="list.php">Back to Products</button>
</p>

<div style="overflow-x:auto;">
<table class="table">
    <thead>
        <tr>
            <th>Feature</th>
            <?php foreach ($products as $p): ?>
                <th style="text-align:center; min-width:160px;">
                    <img src="/photos/<?= $p->photo ?>" alt=""
                         style="width:80px;height:80px;object-fit:cover;border-radius:10px;display:block;margin:0 auto 8px;border:1px solid var(--line);">
                    <?= encode($p->name) ?>
                    <div style="margin-top:8px;">
                        <button class="secondary" data-post="?btn=remove&id=<?= urlencode($p->id) ?>" style="font-size:.75rem; padding:4px 10px;">Remove</button>
                    </div>
                </th>
            <?php endforeach ?>
        </tr>
    </thead>
    <tbody>
        <tr>
            <th>Price</th>
            <?php foreach ($products as $p): ?>
                <td class="right">
                    <?php if (is_on_sale($p)): ?>
                        <span style="text-decoration:line-through; color:var(--muted);">RM <?= sprintf('%.2f', $p->price) ?></span><br>
                        <b style="color:var(--red);">RM <?= sprintf('%.2f', product_price($p)) ?></b>
                    <?php else: ?>
                        RM <?= sprintf('%.2f', $p->price) ?>
                    <?php endif ?>
                </td>
            <?php endforeach ?>
        </tr>
        <tr>
            <th>Origin</th>
            <?php foreach ($products as $p): ?>
                <td><?= encode($p->origin ?: '—') ?></td>
            <?php endforeach ?>
        </tr>
        <tr>
            <th>Roast</th>
            <?php foreach ($products as $p): ?>
                <td><?= encode($p->roast ?: '—') ?></td>
            <?php endforeach ?>
        </tr>
        <tr>
            <th>Tag</th>
            <?php foreach ($products as $p): ?>
                <td>
                    <?php if (!empty($p->tag)): ?>
                        <span class="badge-status process"><?= encode($p->tag) ?></span>
                    <?php else: ?>
                        —
                    <?php endif ?>
                </td>
            <?php endforeach ?>
        </tr>
        <tr>
            <th>Stock</th>
            <?php foreach ($products as $p): ?>
                <td class="right"><?= $p->stock ?></td>
            <?php endforeach ?>
        </tr>
        <tr>
            <th>Description</th>
            <?php foreach ($products as $p): ?>
                <td style="font-size:.85rem; color:var(--muted);"><?= encode($p->description) ?></td>
            <?php endforeach ?>
        </tr>
        <tr>
            <th></th>
            <?php foreach ($products as $p): ?>
                <td style="text-align:center;">
                    <button data-get="detail.php?id=<?= urlencode($p->id) ?>">View</button>
                </td>
            <?php endforeach ?>
        </tr>
    </tbody>
</table>
</div>

<?php endif ?>

<?php
include '../_foot.php';
